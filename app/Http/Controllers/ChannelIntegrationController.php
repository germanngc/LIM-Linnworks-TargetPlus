<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TpListingFeed;
use App\Models\TpOrder;
use App\Models\TpProduct;
use App\Services\TargetPlusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChannelIntegrationController extends Controller
{
    private const PRODUCT_PAGE_SIZE = 100;

    public function __construct(
        private OrdersController $ordersController,
        private DespatchController $despatchController,
    ) {}

    /**
     * Discovery endpoint used by Linnworks to register a user/config and receive an AuthorizationToken.
     */
    public function addNewUser(Request $request): JsonResponse
    {
        $email = (string) ($request->input('Email') ?? $request->input('email') ?? '');
        $userId = (string) ($request->input('UserId') ?? $request->input('userId') ?? '');

        if ($email === '' && $userId === '') {
            return response()->json([
                'Error' => 'Missing user identity (Email/UserId).',
            ], 422);
        }

        $tenant = $this->findOrCreateTenant($email, $userId);

        Log::info('ChannelIntegration AddNewUser called', [
            'tenant_id' => $tenant->id,
            'email' => $email,
            'user_id' => $userId,
            'payload' => $request->all(),
        ]);

        return response()->json([
            'Error' => null,
            'AuthorizationToken' => $tenant->authorization_token,
        ]);
    }

    public function userConfig(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            $token = (string) ($request->input('AuthorizationToken') ?? '');

            if ($token !== '') {
                $tenant = Tenant::query()
                    ->where('authorization_token', $token)
                    ->where('active', true)
                    ->first();

                // Linnworks can call UserConfig with a token after a DB reset.
                // Bootstrap a placeholder tenant so the token can be resolved in subsequent protected endpoints.
                if (!$tenant) {
                    $tenant = Tenant::create([
                        'linnworks_user_id' => 'lw-token-' . substr(md5($token), 0, 24),
                        'linnworks_email' => (string) ($request->input('Email') ?? ''),
                        'linnworks_application_token' => '',
                        'linnworks_token' => '',
                        'linnworks_server' => 'https://api.linnworks.net',
                        'authorization_token' => $token,
                        'target_api_key' => '_pending_',
                        'target_seller_id' => '_pending_',
                        'target_seller_token' => '_pending_',
                        'target_environment' => 'stage',
                        'active' => true,
                    ]);
                }
            }
        }

        if (!$tenant) {
            $tenant = new Tenant([
                'linnworks_email' => '',
                'target_api_key' => '_pending_',
                'target_seller_id' => '_pending_',
                'target_seller_token' => '_pending_',
                'target_environment' => 'stage',
            ]);
        }

        Log::info('ChannelIntegration UserConfig called', [
            'tenant_id' => $tenant->id,
            'payload' => $this->redactedPayload($request),
        ]);

        return response()->json($this->buildUserConfigResponse($tenant));
    }

    public function saveUserConfig(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $configItemsById = collect((array) $request->input('ConfigItems', []))
            ->filter(fn ($item) => is_array($item) && isset($item['ConfigItemId']))
            ->mapWithKeys(function (array $item) {
                $id = (string) ($item['ConfigItemId'] ?? '');
                $selectedValue = (string) ($item['SelectedValue'] ?? '');

                return [$id => $selectedValue];
            });

        $apiKeyFromConfig = (string) ($configItemsById->get('APIKey') ?? '');
        $sellerIdFromConfig = (string) ($configItemsById->get('SellerId') ?? '');
        $sellerTokenFromConfig = (string) ($configItemsById->get('SellerToken') ?? '');
        $environmentFromConfig = (string) ($configItemsById->get('Environment') ?? '');

        $resolvedApiKey = (string) ($request->input('target_api_key') ?? $request->input('ApiKey') ?? ($apiKeyFromConfig !== '' ? $apiKeyFromConfig : $tenant->target_api_key));
        $resolvedSellerId = (string) ($request->input('target_seller_id') ?? $request->input('SellerId') ?? ($sellerIdFromConfig !== '' ? $sellerIdFromConfig : $tenant->target_seller_id));
        $resolvedSellerToken = (string) ($request->input('target_seller_token') ?? $request->input('SellerToken') ?? ($sellerTokenFromConfig !== '' ? $sellerTokenFromConfig : $tenant->target_seller_token));
        $resolvedEnvironment = (string) ($request->input('target_environment') ?? $request->input('Environment') ?? ($environmentFromConfig !== '' ? $environmentFromConfig : $tenant->target_environment));

        if (collect([$resolvedApiKey, $resolvedSellerId, $resolvedSellerToken])->contains(fn ($value) => trim($value) === '')) {
            return response()->json(['Error' => 'API key, seller ID, and seller token are required.']);
        }

        $tenant->update([
            'target_api_key' => $resolvedApiKey,
            'target_seller_id' => $resolvedSellerId,
            'target_seller_token' => $resolvedSellerToken,
            'target_environment' => in_array($resolvedEnvironment, ['stage', 'prod'], true) ? $resolvedEnvironment : 'stage',
            'active' => true,
        ]);

        Log::info('ChannelIntegration SaveUserConfig called', [
            'tenant_id' => $tenant->id,
            'config_item_ids' => $configItemsById->keys()->all(),
        ]);

        $tenant->refresh();

        return response()->json($this->buildUserConfigResponse($tenant));
    }

    public function shippingTags(Request $request): JsonResponse
    {
        $this->logDiscoveryPayload('ShippingTags', $request);

        return response()->json([
            'Error' => null,
            'ShippingTags' => [
                [
                    'Tag' => 'standard',
                    'FriendlyName' => 'Standard',
                    'Site' => '',
                ],
                [
                    'Tag' => 'expedited',
                    'FriendlyName' => 'Expedited',
                    'Site' => '',
                ],
                [
                    'Tag' => 'next_day',
                    'FriendlyName' => 'Next Day',
                    'Site' => '',
                ],
            ],
        ]);
    }

    public function paymentTags(Request $request): JsonResponse
    {
        $this->logDiscoveryPayload('PaymentTags', $request);

        return response()->json([
            'Error' => null,
            'PaymentTags' => [
                [
                    'Tag' => 'target_plus',
                    'FriendlyName' => 'Target Plus',
                    'Site' => '',
                ],
            ],
        ]);
    }

    public function configDeleted(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenant->update(['active' => false]);

        Log::info('ChannelIntegration ConfigDeleted called', [
            'tenant_id' => $tenant->id,
            'payload' => $this->redactedPayload($request),
        ]);

        return response()->json(['Error' => null]);
    }

    public function configTest(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $this->logDiscoveryPayload('ConfigTest', $request);

        if (!$tenant) {
            return response()->json([
                'Error' => 'Invalid or inactive AuthorizationToken',
            ], 401);
        }

        if (!$tenant->hasTargetPlusCredentials()) {
            return response()->json(['Error' => 'Target Plus credentials are incomplete.', 'Success' => false, 'Message' => 'Complete the channel credentials before testing.']);
        }

        $tp = new TargetPlusService($tenant);
        if (!$tp->testConnection()) {
            return response()->json(['Error' => $tp->lastFailureMessage(), 'Success' => false, 'Message' => 'Target Plus connection test failed.']);
        }

        return response()->json([
            'Error' => null,
            'Success' => true,
            'Message' => 'Config test passed.',
            'TenantId' => $tenant->id,
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        return $this->ordersController->index($request);
    }

    public function despatch(Request $request): JsonResponse
    {
        return $this->despatchController->store($request);
    }

    public function cancel(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $cancellation = (array) $request->input('Cancellation', []);
        $orderId = (string) ($cancellation['ReferenceNumber'] ?? '');
        return DB::transaction(function () use ($tenant, $cancellation, $orderId) {
            $order = TpOrder::where('tenant_id', $tenant->id)->where('target_order_id', $orderId)->lockForUpdate()->first();
            if (!$order) return response()->json(['Error' => 'Could not process cancellation.']);
            if ($order->cancel_pushed_to_target) return response()->json(['Error' => null]);
            if (!(new TargetPlusService($tenant))->cancelOrder($orderId, $cancellation)) return response()->json(['Error' => 'Could not process cancellation.']);
            $order->update(['target_status' => 'CANCELED', 'cancel_pushed_to_target' => true, 'target_updated_at' => now()]);
            return response()->json(['Error' => null]);
        });
    }

    public function refund(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $refund = (array) $request->input('Refund', []);
        $orderId = (string) ($refund['ReferenceNumber'] ?? '');
        return DB::transaction(function () use ($tenant, $refund, $orderId) {
            $order = TpOrder::where('tenant_id', $tenant->id)->where('target_order_id', $orderId)->lockForUpdate()->first();
            if (!$order) return response()->json(['Error' => 'Could not process refund.', 'RefundReference' => '']);
            if ($order->refund_reference) return response()->json(['Error' => null, 'RefundReference' => $order->refund_reference]);
            $result = (new TargetPlusService($tenant))->refundOrder($orderId, $refund);
            if ($result === null) return response()->json(['Error' => 'Could not process refund.', 'RefundReference' => '']);
            $reference = (string) ($result['refund_reference'] ?? Str::uuid());
            $order->update(['target_status' => 'REFUNDED', 'target_updated_at' => now(), 'refund_reference' => $reference, 'refunded_to_target_at' => now()]);
            return response()->json(['Error' => null, 'RefundReference' => $reference]);
        });
    }

    public function postSaleOptions(Request $request): JsonResponse
    {
        $this->logDiscoveryPayload('PostSaleOptions', $request);

        return response()->json([
            'Error' => null,
            'CanCancel' => true,
            'CanCancelOrderLines' => true,
            'CanCancelOrderLinesPartially' => false,
            'AutomaticRefundOnCancel' => false,
            'CanRefund' => true,
            'CanAttachRefundToItem' => true,
            'CanAttachRefundToService' => false,
            'RefundShippingTypes' => 0,
            'CanRefundAdditionally' => false,
            'CanReturn' => false,
            'PostSaleReasons' => [],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $pageNumber = max(1, (int) $request->input('PageNumber', 1));
        $offset = ($pageNumber - 1) * self::PRODUCT_PAGE_SIZE;

        $query = TpProduct::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        $total = $query->count();
        $products = $query
            ->orderBy('id')
            ->skip($offset)
            ->take(self::PRODUCT_PAGE_SIZE)
            ->get()
            ->map(function (TpProduct $product): array {
                $raw = $product->raw_payload ?? [];

                return [
                    'SKU' => (string) $product->sku,
                    'Title' => (string) ($product->title ?: $product->sku),
                    'Quantity' => max(0, (int) $product->available_quantity),
                    'Price' => (float) $product->price,
                    'Reference' => mb_substr((string) ($product->tcin ?: $product->external_id ?: $product->sku), 0, 64),
                ];
            })
            ->values()
            ->all();

        Log::info('ChannelIntegration Products returned', [
            'tenant_id' => $tenant->id,
            'page_number' => $pageNumber,
            'returned' => count($products),
            'total' => $total,
        ]);

        return response()->json([
            'Error' => null,
            'HasMorePages' => ($offset + self::PRODUCT_PAGE_SIZE) < $total,
            'Products' => $products,
        ]);
    }

    public function inventoryUpdate(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $products = (array) $request->input('Products', []);

        $tp = new TargetPlusService($tenant);
        $results = [];

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $sku = trim((string) ($product['SKU'] ?? ''));
            $reference = trim((string) ($product['Reference'] ?? ''));
            $quantity = max(0, (int) ($product['Quantity'] ?? 0));

            if ($sku === '') {
                $results[] = [
                    'SKU' => '',
                    'Error' => 'Missing SKU',
                ];
                continue;
            }

            $tpProduct = TpProduct::query()
                ->where('tenant_id', $tenant->id)
                ->where(function ($query) use ($sku, $reference) {
                    $query->where('sku', $sku);

                    if ($reference !== '') {
                        $query->orWhere('external_id', $reference)
                            ->orWhere('tcin', $reference);
                    }
                })
                ->first();

            if (!$tpProduct) {
                $results[] = [
                    'SKU' => $sku,
                    'Error' => 'SKU is not mapped to a Target Plus product',
                ];
                continue;
            }

            $remoteIdentifier = $tpProduct->tcin ?: ($reference !== '' ? $reference : $sku);
            $updated = $tp->updateProductInventory($remoteIdentifier, $quantity);

            if (!$updated) {
                $results[] = [
                    'SKU' => $sku,
                    'Error' => 'Target Plus inventory update failed',
                ];
                continue;
            }

            $tpProduct->update([
                'available_quantity' => $quantity,
                'inventory_synced_at' => now(),
            ]);

            $results[] = [
                'SKU' => $sku,
                'Error' => null,
            ];
        }

        Log::info('ChannelIntegration InventoryUpdate processed', [
            'tenant_id' => $tenant->id,
            'requested' => count($products),
            'results' => $results,
        ]);

        return response()->json([
            'Error' => null,
            'Products' => $results,
        ]);
    }

    public function priceUpdate(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tp = new TargetPlusService($tenant);
        $results = [];
        foreach ((array) $request->input('Products', []) as $item) {
            $sku = trim((string) ($item['SKU'] ?? ''));
            $reference = trim((string) ($item['Reference'] ?? ''));
            $product = TpProduct::where('tenant_id', $tenant->id)
                ->where(fn ($q) => $q->where('sku', $sku)->orWhere('tcin', $reference)->orWhere('external_id', $reference))->first();
            if (!$product) { $results[] = ['SKU' => $sku, 'Error' => 'SKU does not exist']; continue; }
            $price = max(0, (float) ($item['Price'] ?? 0));
            if (!$tp->updateProductPrice($product->tcin, $price)) { $results[] = ['SKU' => $sku, 'Error' => 'Target Plus price update failed']; continue; }
            $product->update(['price' => $price]);
            $results[] = ['SKU' => $sku, 'Error' => null];
        }
        return response()->json(['Error' => null, 'Products' => $results]);
    }

    public function getConfiguratorSettings(Request $request): JsonResponse
    {
        Log::info('ChannelIntegration GetConfiguratorSettings called', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'Error' => null,
            'Settings' => [[
                'GroupName' => 'GENERAL',
                'ConfigItemId' => 'Condition',
                'Subtitle' => 'Product',
                'SubTitleSortOrder' => 1,
                'ItemSortOrder' => 1,
                'Description' => 'Condition submitted with the Target Plus listing.',
                'FriendlyName' => 'Condition',
                'MustBeSpecified' => true,
                'ExpectedType' => 'LIST',
                'ValueOptions' => ['New'],
                'InitialValues' => ['New'],
                'IsMultiOption' => false,
                'ValueFromOptionsList' => true,
                'RegExValidation' => '',
                'RegExError' => '',
                'IsWizardOnly' => false,
            ]],
            'MaxDescriptionLength' => 10000,
            'ImageSettings' => [
                'Type' => 1,
                'MaxImages' => 10,
                'MaxVariantImages' => 0,
                'ImageTags' => [],
            ],
            'MaxCategoryCount' => 1,
            'MaxCustomAttributeLength' => 255,
            'IsCustomHtmlSupported' => false,
            'IsCustomAttributesAllowed' => true,
            'IsVariationsAllowed' => false,
            'HasMainVariationPrice' => false,
            'IsTitleInVariation' => false,
            'HasVariationAttributeDisplayName' => false,
            'IsPriceInVariation' => false,
            'IsShippingListingSpecific' => false,
            'IsPaymentListingSpecific' => false,
        ]);
    }

    public function getCategories(Request $request): JsonResponse
    {
        $this->logDiscoveryPayload('GetCategories', $request);

        return response()->json([
            'Error' => null,
            'HasMorePages' => false,
            'Categories' => [
                ['CategoryId' => 1, 'CategoryName' => 'Apparel'],
                ['CategoryId' => 2, 'CategoryName' => 'Home'],
                ['CategoryId' => 3, 'CategoryName' => 'General Merchandise'],
            ],
        ]);
    }

    public function getAttributesByCategory(Request $request): JsonResponse
    {
        $this->logDiscoveryPayload('GetAttributesByCategory', $request);

        return response()->json([
            'Error' => null,
            'Attributes' => [
                [
                    'ID' => 'Brand', 'FriendlyName' => 'Brand', 'Description' => 'Product brand.',
                    'MustBeSpecified' => 'Required', 'ExpectedType' => 'STRING', 'ValueOptions' => [],
                    'ValueFromOptionsList' => false, 'MaxAttributeUse' => 1, 'AttribueReadFrom' => 'Parent',
                    'RegExValidation' => '', 'RegExError' => '',
                ],
                [
                    'ID' => 'Barcode', 'FriendlyName' => 'Barcode', 'Description' => 'UPC/EAN/GTIN.',
                    'MustBeSpecified' => 'Desired', 'ExpectedType' => 'STRING', 'ValueOptions' => [],
                    'ValueFromOptionsList' => false, 'MaxAttributeUse' => 1, 'AttribueReadFrom' => 'Parent',
                    'RegExValidation' => '', 'RegExError' => '',
                ],
            ],
        ]);
    }

    public function getVariationsByCategory(Request $request): JsonResponse
    {
        $this->logDiscoveryPayload('GetVariationsByCategory', $request);

        return response()->json([
            'Error' => null,
            'MaxVariationAttributes' => 0,
            'NeededVariations' => [],
        ]);
    }

    public function listingUpdate(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $operation = (int) $request->input('Type', 0) === 1 ? 'update' : 'create';
        $requestHash = hash('sha256', json_encode(['Type' => $request->input('Type', 0), 'Listings' => $request->input('Listings', []), 'Settings' => $request->input('Settings', [])]));
        $existingFeed = TpListingFeed::where('tenant_id', $tenant->id)->where('operation', $operation)->where('request_hash', $requestHash)->first();
        if ($existingFeed) return response()->json(['Error' => null, 'ChannelFeedId' => $existingFeed->channel_feed_id]);
        $tp = new TargetPlusService($tenant);
        $productFeeds = [];

        foreach ((array) $request->input('Listings', []) as $listing) {
            if (!is_array($listing)) {
                continue;
            }

            $sku = trim((string) ($listing['SKU'] ?? ''));
            $templateId = (int) ($listing['TemplateId'] ?? 0);
            $externalId = trim((string) ($listing['ExternalListingId'] ?? ''));
            $payload = $this->mapListingToTargetProduct($listing);
            $response = $sku === '' ? null : ($operation === 'update' && $externalId !== ''
                ? $tp->updateProduct($externalId, $payload)
                : $tp->createProduct($payload));

            if ($response === null) {
                $productFeeds[] = $this->listingFeedResult($sku, $templateId, $externalId, null, 'Target Plus listing request failed.');
                continue;
            }

            $reference = (string) ($response['tcin'] ?? $response['external_id'] ?? ($externalId !== '' ? $externalId : $sku));
            TpProduct::updateOrCreate(
                ['tenant_id' => $tenant->id, 'tcin' => $reference],
                [
                    'external_id' => (string) ($response['external_id'] ?? $reference),
                    'sku' => $sku,
                    'title' => (string) ($listing['Title'] ?? $sku),
                    'status' => 'ACTIVE',
                    'available_quantity' => max(0, (int) ($listing['Quantity'] ?? 0)),
                    'price' => max(0, (float) ($listing['Price'] ?? 0)),
                    'listing_active' => true,
                    'raw_payload' => array_merge($payload, $response),
                ]
            );
            $productFeeds[] = $this->listingFeedResult($sku, $templateId, $reference, $reference);
        }

        $feed = $this->storeListingFeed($tenant, $operation, $productFeeds, $requestHash);

        return response()->json(['Error' => null, 'ChannelFeedId' => $feed->channel_feed_id]);
    }

    public function listingDelete(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $tp = new TargetPlusService($tenant);
        $requestHash = hash('sha256', json_encode($request->input('ExternalListingIds', [])));
        $existingFeed = TpListingFeed::where('tenant_id', $tenant->id)->where('operation', 'delete')->where('request_hash', $requestHash)->first();
        if ($existingFeed) return response()->json(['Error' => null, 'ChannelFeedId' => $existingFeed->channel_feed_id]);
        $productFeeds = [];

        foreach ((array) $request->input('ExternalListingIds', []) as $listing) {
            $sku = (string) ($listing['ChannelSKU'] ?? '');
            $externalId = (string) ($listing['ExternalListingId'] ?? '');
            $templateId = (int) ($listing['TemplateId'] ?? 0);
            $deleted = $externalId !== '' && $tp->deleteProduct($externalId);
            if ($deleted) {
                TpProduct::where('tenant_id', $tenant->id)
                    ->where(fn ($query) => $query->where('tcin', $externalId)->orWhere('external_id', $externalId))
                    ->update(['listing_active' => false, 'status' => 'INACTIVE']);
            }
            $productFeeds[] = $this->listingFeedResult($sku, $templateId, $externalId, $externalId, $deleted ? null : 'Target Plus listing delete failed.');
        }

        $feed = $this->storeListingFeed($tenant, 'delete', $productFeeds, $requestHash);

        return response()->json(['Error' => null, 'ChannelFeedId' => $feed->channel_feed_id]);
    }

    public function checkFeed(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $feed = TpListingFeed::where('tenant_id', $tenant->id)
            ->where('channel_feed_id', (string) $request->input('ChannelFeedId'))
            ->first();

        if (!$feed) {
            return response()->json(['Error' => 'Unknown ChannelFeedId.', 'IsFeedReady' => true, 'ProductFeeds' => []]);
        }

        return response()->json([
            'Error' => null,
            'IsFeedReady' => true,
            'ProductFeeds' => $feed->product_feeds,
        ]);
    }

    public function oauthAuthorize(Request $request): JsonResponse
    {
        $this->logDiscoveryPayload('OAuthAuthorize', $request);

        return response()->json([
            'access_token' => (string) Str::uuid(),
            'token_type' => 'bearer',
            'expires_in' => 3600,
            'scope' => (string) ($request->input('scope') ?? 'read write'),
        ]);
    }

    private function mapListingToTargetProduct(array $listing): array
    {
        $attributes = collect((array) ($listing['Attributes'] ?? []))
            ->filter(fn ($attribute) => is_array($attribute))
            ->mapWithKeys(fn ($attribute) => [
                (string) ($attribute['AttributeID'] ?? $attribute['Name'] ?? '') => (string) ($attribute['AttributeValue'] ?? $attribute['Value'] ?? ''),
            ])
            ->filter(fn ($value, $key) => $key !== '')
            ->all();

        return [
            'sku' => (string) ($listing['SKU'] ?? ''),
            'title' => (string) ($listing['Title'] ?? ''),
            'description' => (string) ($listing['Description'] ?? ''),
            'available_quantity' => max(0, (int) ($listing['Quantity'] ?? 0)),
            'price' => max(0, (float) ($listing['Price'] ?? 0)),
            'category' => (string) (($listing['Categories'][0] ?? '3')),
            'images' => array_values((array) ($listing['Images'] ?? [])),
            'attributes' => $attributes,
        ];
    }

    private function listingFeedResult(
        string $sku,
        int $templateId,
        string $externalListingId,
        ?string $reference,
        ?string $error = null,
    ): array {
        return [
            'SKU' => $sku,
            'TemplateId' => $templateId,
            'ExternalListingId' => $externalListingId,
            'URL' => '',
            'Messages' => $error === null ? [] : [['Type' => 'Error', 'Message' => $error]],
            'ChannelReferences' => $reference === null ? [] : [['SKU' => $sku, 'Reference' => $reference]],
        ];
    }

    private function storeListingFeed(Tenant $tenant, string $operation, array $productFeeds, string $requestHash): TpListingFeed
    {
        return TpListingFeed::create([
            'tenant_id' => $tenant->id,
            'channel_feed_id' => (string) Str::uuid(),
            'operation' => $operation,
            'request_hash' => $requestHash,
            'product_feeds' => $productFeeds,
        ]);
    }

    private function findOrCreateTenant(string $email, string $userId): Tenant
    {
        $tenant = null;
        $resolvedUserId = trim($userId);

        if ($resolvedUserId === '' && $email !== '') {
            $resolvedUserId = 'lw-' . md5($email);
        }

        if ($resolvedUserId !== '') {
            $tenant = Tenant::where('linnworks_user_id', $resolvedUserId)->first();
        }

        if (!$tenant && $email !== '') {
            $tenant = Tenant::where('linnworks_email', $email)->first();
        }

        if ($tenant) {
            $tenant->linnworks_user_id = $resolvedUserId !== '' ? $resolvedUserId : $tenant->linnworks_user_id;
            $tenant->linnworks_email = $email !== '' ? $email : $tenant->linnworks_email;
            $tenant->active = true;

            if (!$tenant->authorization_token) {
                $tenant->authorization_token = Tenant::generateAuthorizationToken();
            }

            $tenant->save();

            return $tenant;
        }

        return Tenant::create([
            'linnworks_user_id' => $resolvedUserId !== '' ? $resolvedUserId : 'lw-' . md5($email ?: uniqid('tenant-', true)),
            'linnworks_email' => $email,
            'linnworks_application_token' => '',
            'linnworks_token' => '',
            'linnworks_server' => 'https://api.linnworks.net',
            'authorization_token' => Tenant::generateAuthorizationToken(),
            'target_api_key' => '_pending_',
            'target_seller_id' => '_pending_',
            'target_seller_token' => '_pending_',
            'target_environment' => 'stage',
            'active' => true,
        ]);
    }

    private function logDiscoveryPayload(string $endpoint, Request $request): void
    {
        Log::info('ChannelIntegration endpoint hit', [
            'endpoint' => $endpoint,
            'tenant_id' => optional($request->attributes->get('tenant'))->id,
            'payload' => $this->redactedPayload($request),
        ]);
    }

    private function redactedPayload(Request $request): array
    {
        $payload = $request->all();
        foreach (['AuthorizationToken', 'authorizationToken', 'target_api_key', 'ApiKey', 'SellerToken', 'target_seller_token'] as $key) {
            if (array_key_exists($key, $payload)) $payload[$key] = '[REDACTED]';
        }
        if (isset($payload['ConfigItems']) && is_array($payload['ConfigItems'])) {
            $payload['ConfigItems'] = array_map(function ($item) {
                if (is_array($item) && in_array($item['ConfigItemId'] ?? null, ['APIKey', 'SellerToken'], true)) $item['SelectedValue'] = '[REDACTED]';
                return $item;
            }, $payload['ConfigItems']);
        }
        return $payload;
    }

    private function buildUserConfigResponse(Tenant $tenant): array
    {
        $isConfigured = !str_starts_with($tenant->target_api_key, '_pending_')
            && !str_starts_with($tenant->target_seller_id, '_pending_')
            && !str_starts_with($tenant->target_seller_token, '_pending_');

        $stepName = $isConfigured ? 'UserConfig' : 'AddCredentials';
        $wizardStepTitle = $isConfigured ? 'Configuration Complete' : 'Add Target Plus Credentials';
        $wizardStepDescription = $isConfigured
            ? 'Credentials are configured. You can now save and continue.'
            : 'Enter your Target Plus credentials to complete this integration.';

        $configItems = $isConfigured
            ? []
            : [
                [
                    'ConfigItemId' => 'APIKey',
                    'Name' => 'API Key',
                    'Description' => 'Target Plus API key.',
                    'GroupName' => 'Credentials',
                    'SortOrder' => 1,
                    'SelectedValue' => '',
                    'RegExValidation' => null,
                    'RegExError' => null,
                    'MustBeSpecified' => true,
                    'ReadOnly' => false,
                    'ListValues' => [],
                    'ValueType' => 'PASSWORD',
                    'HidesHeaderAttribute' => false,
                ],
                [
                    'ConfigItemId' => 'SellerId',
                    'Name' => 'Seller ID',
                    'Description' => 'Target Plus seller ID.',
                    'GroupName' => 'Credentials',
                    'SortOrder' => 2,
                    'SelectedValue' => '',
                    'RegExValidation' => null,
                    'RegExError' => null,
                    'MustBeSpecified' => true,
                    'ReadOnly' => false,
                    'ListValues' => [],
                    'ValueType' => 'STRING',
                    'HidesHeaderAttribute' => false,
                ],
                [
                    'ConfigItemId' => 'SellerToken',
                    'Name' => 'Seller Token',
                    'Description' => 'Target Plus seller token.',
                    'GroupName' => 'Credentials',
                    'SortOrder' => 3,
                    'SelectedValue' => '',
                    'RegExValidation' => null,
                    'RegExError' => null,
                    'MustBeSpecified' => true,
                    'ReadOnly' => false,
                    'ListValues' => [],
                    'ValueType' => 'PASSWORD',
                    'HidesHeaderAttribute' => false,
                ],
                [
                    'ConfigItemId' => 'Environment',
                    'Name' => 'Environment',
                    'Description' => 'Target Plus environment.',
                    'GroupName' => 'Credentials',
                    'SortOrder' => 4,
                    'SelectedValue' => $tenant->target_environment === 'prod' ? 'prod' : 'stage',
                    'RegExValidation' => null,
                    'RegExError' => null,
                    'MustBeSpecified' => true,
                    'ReadOnly' => false,
                    'ListValues' => [
                        ['Display' => 'Stage', 'Value' => 'stage'],
                        ['Display' => 'Production', 'Value' => 'prod'],
                    ],
                    'ValueType' => 'LIST',
                    'HidesHeaderAttribute' => false,
                ],
            ];

        return [
            'Error' => null,
            'StepName' => $stepName,
            'AccountName' => (string) ($tenant->linnworks_email ?: 'Target Plus Channel'),
            'WizardStepDescription' => $wizardStepDescription,
            'WizardStepTitle' => $wizardStepTitle,
            'ConfigItems' => $configItems,
            'GlobalConfigSettings' => [
                'PriceTags' => [],
                'IsPriceChangeVisible' => true,
                'IsShippingMappingVisible' => true,
                'IsPaymentMappingVisible' => true,
                'IsCancellationsVisible' => true,
                'IsRefundsVisible' => true,
                'IsInventoryMappingVisible' => true,
                'IsMultiLocationEnabled' => false,
                'IsMultilocationEnabled' => false,
                'MultiLocationInventory' => [
                    'OrdersLocationIdAlwaysProvided' => false,
                    'AutoPopulateLocations' => false,
                    'AutoPopulatedLocations' => false,
                    'Locations' => [],
                ],
            ],
        ];
    }
}
