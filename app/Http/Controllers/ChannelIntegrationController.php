<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\VendorListingFeed;
use App\Models\VendorOrder;
use App\Models\VendorProduct;
use App\Services\VendorApiService;
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
                        'vendor_api_key' => '_pending_',
                        'vendor_account_id' => '_pending_',
                        'vendor_account_token' => '_pending_',
                        'vendor_environment' => 'stage',
                        'active' => true,
                    ]);
                }
            }
        }

        if (!$tenant) {
            $tenant = new Tenant([
                'linnworks_email' => '',
                'vendor_api_key' => '_pending_',
                'vendor_account_id' => '_pending_',
                'vendor_account_token' => '_pending_',
                'vendor_environment' => 'stage',
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
        $accountIdFromConfig = (string) ($configItemsById->get('AccountId') ?? '');
        $accountTokenFromConfig = (string) ($configItemsById->get('AccountToken') ?? '');
        $environmentFromConfig = (string) ($configItemsById->get('Environment') ?? '');

        $resolvedApiKey = (string) ($request->input('vendor_api_key') ?? $request->input('ApiKey') ?? ($apiKeyFromConfig !== '' ? $apiKeyFromConfig : $tenant->vendor_api_key));
        $resolvedAccountId = (string) ($request->input('vendor_account_id') ?? $request->input('AccountId') ?? ($accountIdFromConfig !== '' ? $accountIdFromConfig : $tenant->vendor_account_id));
        $resolvedAccountToken = (string) ($request->input('vendor_account_token') ?? $request->input('AccountToken') ?? ($accountTokenFromConfig !== '' ? $accountTokenFromConfig : $tenant->vendor_account_token));
        $resolvedEnvironment = (string) ($request->input('vendor_environment') ?? $request->input('Environment') ?? ($environmentFromConfig !== '' ? $environmentFromConfig : $tenant->vendor_environment));

        if (collect([$resolvedApiKey, $resolvedAccountId, $resolvedAccountToken])->contains(fn ($value) => trim($value) === '')) {
            return response()->json(['Error' => 'API key, account ID, and account token are required.']);
        }

        $tenant->update([
            'vendor_api_key' => $resolvedApiKey,
            'vendor_account_id' => $resolvedAccountId,
            'vendor_account_token' => $resolvedAccountToken,
            'vendor_environment' => in_array($resolvedEnvironment, ['stage', 'prod'], true) ? $resolvedEnvironment : 'stage',
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
                    'Tag' => 'vendor_api',
                    'FriendlyName' => 'Vendor',
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

        if (!$tenant->hasVendorCredentials()) {
            return response()->json(['Error' => 'Vendor credentials are incomplete.', 'Success' => false, 'Message' => 'Complete the channel credentials before testing.']);
        }

        $tp = new VendorApiService($tenant);
        if (!$tp->testConnection()) {
            return response()->json(['Error' => $tp->lastFailureMessage(), 'Success' => false, 'Message' => 'Vendor connection test failed.']);
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
            $order = VendorOrder::where('tenant_id', $tenant->id)->where('vendor_order_id', $orderId)->lockForUpdate()->first();
            if (!$order) return response()->json(['Error' => 'Could not process cancellation.']);
            if ($order->cancel_pushed_to_vendor) return response()->json(['Error' => null]);
            if (!(new VendorApiService($tenant))->cancelOrder($orderId, $cancellation)) return response()->json(['Error' => 'Could not process cancellation.']);
            $order->update(['vendor_status' => 'CANCELED', 'cancel_pushed_to_vendor' => true, 'vendor_updated_at' => now()]);
            return response()->json(['Error' => null]);
        });
    }

    public function refund(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $refund = (array) $request->input('Refund', []);
        $orderId = (string) ($refund['ReferenceNumber'] ?? '');
        return DB::transaction(function () use ($tenant, $refund, $orderId) {
            $order = VendorOrder::where('tenant_id', $tenant->id)->where('vendor_order_id', $orderId)->lockForUpdate()->first();
            if (!$order) return response()->json(['Error' => 'Could not process refund.', 'RefundReference' => '']);
            if ($order->refund_reference) return response()->json(['Error' => null, 'RefundReference' => $order->refund_reference]);
            $result = (new VendorApiService($tenant))->refundOrder($orderId, $refund);
            if ($result === null) return response()->json(['Error' => 'Could not process refund.', 'RefundReference' => '']);
            $reference = (string) ($result['refund_reference'] ?? Str::uuid());
            $order->update(['vendor_status' => 'REFUNDED', 'vendor_updated_at' => now(), 'refund_reference' => $reference, 'refunded_to_vendor_at' => now()]);
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

        $query = VendorProduct::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        $total = $query->count();
        $products = $query
            ->orderBy('id')
            ->skip($offset)
            ->take(self::PRODUCT_PAGE_SIZE)
            ->get()
            ->map(function (VendorProduct $product): array {
                $raw = $product->raw_payload ?? [];

                return [
                    'SKU' => (string) $product->sku,
                    'Title' => (string) ($product->title ?: $product->sku),
                    'Quantity' => max(0, (int) $product->available_quantity),
                    'Price' => (float) $product->price,
                    'Reference' => mb_substr((string) ($product->vendor_reference ?: $product->external_id ?: $product->sku), 0, 64),
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

        $tp = new VendorApiService($tenant);
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

            $tpProduct = VendorProduct::query()
                ->where('tenant_id', $tenant->id)
                ->where(function ($query) use ($sku, $reference) {
                    $query->where('sku', $sku);

                    if ($reference !== '') {
                        $query->orWhere('external_id', $reference)
                            ->orWhere('vendor_reference', $reference);
                    }
                })
                ->first();

            if (!$tpProduct) {
                $results[] = [
                    'SKU' => $sku,
                    'Error' => 'SKU is not mapped to a Vendor product',
                ];
                continue;
            }

            $remoteIdentifier = $tpProduct->vendor_reference ?: ($reference !== '' ? $reference : $sku);
            $updated = $tp->updateProductInventory($remoteIdentifier, $quantity);

            if (!$updated) {
                $results[] = [
                    'SKU' => $sku,
                    'Error' => 'Vendor inventory update failed',
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
        $tp = new VendorApiService($tenant);
        $results = [];
        foreach ((array) $request->input('Products', []) as $item) {
            $sku = trim((string) ($item['SKU'] ?? ''));
            $reference = trim((string) ($item['Reference'] ?? ''));
            $product = VendorProduct::where('tenant_id', $tenant->id)
                ->where(fn ($q) => $q->where('sku', $sku)->orWhere('vendor_reference', $reference)->orWhere('external_id', $reference))->first();
            if (!$product) { $results[] = ['SKU' => $sku, 'Error' => 'SKU does not exist']; continue; }
            $price = max(0, (float) ($item['Price'] ?? 0));
            if (!$tp->updateProductPrice($product->vendor_reference, $price)) { $results[] = ['SKU' => $sku, 'Error' => 'Vendor price update failed']; continue; }
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
                'Description' => 'Condition submitted with the Vendor listing.',
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
        $operation = $this->listingOperation($request->input('Type', 0));
        $listings = $this->requestArray($request, 'Listings');
        $settings = $this->requestArray($request, 'Settings');
        $requestHash = hash('sha256', json_encode(['Type' => $operation, 'Listings' => $listings, 'Settings' => $settings]));
        $existingFeed = VendorListingFeed::where('tenant_id', $tenant->id)->where('operation', $operation)->where('request_hash', $requestHash)->first();
        if ($existingFeed && !$this->listingFeedHasErrors($existingFeed)) {
            Log::info('ChannelIntegration ListingUpdate duplicate request', [
                'tenant_id' => $tenant->id,
                'operation' => $operation,
                'channel_feed_id' => $existingFeed->channel_feed_id,
            ]);

            return response()->json(['Error' => null, 'ChannelFeedId' => $existingFeed->channel_feed_id]);
        }
        if ($existingFeed) {
            Log::info('ChannelIntegration ListingUpdate retrying failed feed', [
                'tenant_id' => $tenant->id,
                'operation' => $operation,
                'channel_feed_id' => $existingFeed->channel_feed_id,
            ]);
        }
        $tp = new VendorApiService($tenant);
        $productFeeds = [];

        Log::info('ChannelIntegration ListingUpdate received', [
            'tenant_id' => $tenant->id,
            'operation' => $operation,
            'listing_count' => count($listings),
            'skus' => collect($listings)->filter(fn ($listing) => is_array($listing))->pluck('SKU')->filter()->values()->all(),
            'payload' => $this->redactedPayload($request),
        ]);

        foreach ($listings as $listing) {
            if (!is_array($listing)) {
                continue;
            }

            $sku = trim((string) ($listing['SKU'] ?? ''));
            $templateId = (int) ($listing['TemplateId'] ?? 0);
            $externalId = trim((string) ($listing['ExternalListingId'] ?? ''));
            $payload = $this->mapListingToVendorProduct($listing);

            Log::info('ChannelIntegration ListingUpdate processing listing', [
                'tenant_id' => $tenant->id,
                'operation' => $operation,
                'template_id' => $templateId,
                'sku' => $sku,
                'external_listing_id' => $externalId,
                'vendor_payload' => [
                    'sku' => $payload['sku'],
                    'title' => $payload['title'],
                    'quantity' => $payload['available_quantity'],
                    'price' => $payload['price'],
                    'category' => $payload['category'],
                    'image_count' => count($payload['images']),
                    'attribute_keys' => array_keys($payload['attributes']),
                ],
            ]);

            $response = $sku === '' ? null : ($operation === 'update' && $externalId !== ''
                ? $tp->updateProduct($externalId, $payload)
                : $tp->createProduct($payload));

            if ($response === null) {
                Log::warning('ChannelIntegration ListingUpdate vendor request failed', [
                    'tenant_id' => $tenant->id,
                    'operation' => $operation,
                    'template_id' => $templateId,
                    'sku' => $sku,
                    'external_listing_id' => $externalId,
                    'vendor_failure' => $sku === '' ? 'Missing SKU.' : $tp->lastFailureMessage(),
                ]);

                $productFeeds[] = $this->listingFeedResult($sku, $templateId, $externalId, null, $sku === '' ? 'Missing SKU.' : 'Vendor listing request failed.');
                continue;
            }

            $reference = (string) ($response['vendor_reference'] ?? $response['external_id'] ?? ($externalId !== '' ? $externalId : $sku));
            $product = VendorProduct::updateOrCreate(
                ['tenant_id' => $tenant->id, 'vendor_reference' => $reference],
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

            Log::info('ChannelIntegration ListingUpdate mapped to vendor product', [
                'tenant_id' => $tenant->id,
                'operation' => $operation,
                'template_id' => $templateId,
                'sku' => $sku,
                'vendor_product_id' => $product->id,
                'vendor_reference' => $reference,
                'external_listing_id' => (string) ($response['external_id'] ?? $reference),
                'listing_active' => $product->listing_active,
            ]);

            $productFeeds[] = $this->listingFeedResult($sku, $templateId, $reference, $reference);
        }

        $feed = $this->storeListingFeed($tenant, $operation, $productFeeds, $requestHash, $existingFeed);

        Log::info('ChannelIntegration ListingUpdate stored feed', [
            'tenant_id' => $tenant->id,
            'operation' => $operation,
            'channel_feed_id' => $feed->channel_feed_id,
            'product_feed_count' => count($productFeeds),
        ]);

        return response()->json(['Error' => null, 'ChannelFeedId' => $feed->channel_feed_id]);
    }

    public function listingDelete(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $tp = new VendorApiService($tenant);
        $externalListingIds = $this->requestArray($request, 'ExternalListingIds');
        $requestHash = hash('sha256', json_encode($externalListingIds));
        $existingFeed = VendorListingFeed::where('tenant_id', $tenant->id)->where('operation', 'delete')->where('request_hash', $requestHash)->first();
        if ($existingFeed && !$this->listingFeedHasErrors($existingFeed)) {
            Log::info('ChannelIntegration ListingDelete duplicate request', [
                'tenant_id' => $tenant->id,
                'channel_feed_id' => $existingFeed->channel_feed_id,
            ]);

            return response()->json(['Error' => null, 'ChannelFeedId' => $existingFeed->channel_feed_id]);
        }
        if ($existingFeed) {
            Log::info('ChannelIntegration ListingDelete retrying failed feed', [
                'tenant_id' => $tenant->id,
                'channel_feed_id' => $existingFeed->channel_feed_id,
            ]);
        }
        $productFeeds = [];

        Log::info('ChannelIntegration ListingDelete received', [
            'tenant_id' => $tenant->id,
            'listing_count' => count($externalListingIds),
            'payload' => $this->redactedPayload($request),
        ]);

        foreach ($externalListingIds as $listing) {
            $sku = (string) ($listing['ChannelSKU'] ?? '');
            $externalId = (string) ($listing['ExternalListingId'] ?? '');
            $templateId = (int) ($listing['TemplateId'] ?? 0);
            $deleted = $externalId !== '' && $tp->deleteProduct($externalId);
            if ($deleted) {
                VendorProduct::where('tenant_id', $tenant->id)
                    ->where(fn ($query) => $query->where('vendor_reference', $externalId)->orWhere('external_id', $externalId))
                    ->update(['listing_active' => false, 'status' => 'INACTIVE']);
            }
            $productFeeds[] = $this->listingFeedResult($sku, $templateId, $externalId, $externalId, $deleted ? null : 'Vendor listing delete failed.');
        }

        $feed = $this->storeListingFeed($tenant, 'delete', $productFeeds, $requestHash, $existingFeed);

        Log::info('ChannelIntegration ListingDelete stored feed', [
            'tenant_id' => $tenant->id,
            'channel_feed_id' => $feed->channel_feed_id,
            'product_feed_count' => count($productFeeds),
        ]);

        return response()->json(['Error' => null, 'ChannelFeedId' => $feed->channel_feed_id]);
    }

    public function checkFeed(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $feed = VendorListingFeed::where('tenant_id', $tenant->id)
            ->where('channel_feed_id', (string) $request->input('ChannelFeedId'))
            ->first();

        Log::info('ChannelIntegration CheckFeed received', [
            'tenant_id' => $tenant->id,
            'channel_feed_id' => (string) $request->input('ChannelFeedId'),
            'found' => $feed !== null,
            'product_feed_count' => $feed ? count((array) $feed->product_feeds) : 0,
            'template_ids' => $feed ? collect($feed->product_feeds)->pluck('TemplateId')->filter()->values()->all() : [],
        ]);

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

    private function mapListingToVendorProduct(array $listing): array
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
            'Messages' => $error === null ? null : [['Type' => 1, 'Message' => $error]],
            'ChannelReferences' => $reference === null ? [] : [['SKU' => $sku, 'Reference' => $reference]],
        ];
    }

    private function listingOperation(mixed $type): string
    {
        if (is_string($type) && strcasecmp($type, 'UPDATE') === 0) {
            return 'update';
        }

        return (int) $type === 1 ? 'update' : 'create';
    }

    private function requestArray(Request $request, string $key): array
    {
        $value = $request->input($key, $request->input(lcfirst($key), []));

        return is_array($value) ? $value : [];
    }

    private function listingFeedHasErrors(VendorListingFeed $feed): bool
    {
        foreach ((array) $feed->product_feeds as $productFeed) {
            if (!empty($productFeed['Messages'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    private function storeListingFeed(Tenant $tenant, string $operation, array $productFeeds, string $requestHash, ?VendorListingFeed $existingFeed = null): VendorListingFeed
    {
        if ($existingFeed) {
            $existingFeed->update([
                'product_feeds' => $productFeeds,
            ]);

            return $existingFeed->refresh();
        }

        return VendorListingFeed::create([
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
            'vendor_api_key' => '_pending_',
            'vendor_account_id' => '_pending_',
            'vendor_account_token' => '_pending_',
            'vendor_environment' => 'stage',
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
        foreach (['AuthorizationToken', 'authorizationToken', 'vendor_api_key', 'ApiKey', 'AccountToken', 'vendor_account_token'] as $key) {
            if (array_key_exists($key, $payload)) $payload[$key] = '[REDACTED]';
        }
        if (isset($payload['ConfigItems']) && is_array($payload['ConfigItems'])) {
            $payload['ConfigItems'] = array_map(function ($item) {
                if (is_array($item) && in_array($item['ConfigItemId'] ?? null, ['APIKey', 'AccountToken'], true)) $item['SelectedValue'] = '[REDACTED]';
                return $item;
            }, $payload['ConfigItems']);
        }
        return $payload;
    }

    private function buildUserConfigResponse(Tenant $tenant): array
    {
        $isConfigured = !str_starts_with($tenant->vendor_api_key, '_pending_')
            && !str_starts_with($tenant->vendor_account_id, '_pending_')
            && !str_starts_with($tenant->vendor_account_token, '_pending_');

        $stepName = $isConfigured ? 'UserConfig' : 'AddCredentials';
        $wizardStepTitle = $isConfigured ? 'Configuration Complete' : 'Add Vendor Credentials';
        $wizardStepDescription = $isConfigured
            ? 'Credentials are configured. You can now save and continue.'
            : 'Enter your Vendor credentials to complete this integration.';

        $configItems = $isConfigured
            ? []
            : [
                [
                    'ConfigItemId' => 'APIKey',
                    'Name' => 'API Key',
                    'Description' => 'Vendor API key.',
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
                    'ConfigItemId' => 'AccountId',
                    'Name' => 'Account ID',
                    'Description' => 'Vendor account ID.',
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
                    'ConfigItemId' => 'AccountToken',
                    'Name' => 'Account Token',
                    'Description' => 'Vendor account token.',
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
                    'Description' => 'Vendor environment.',
                    'GroupName' => 'Credentials',
                    'SortOrder' => 4,
                    'SelectedValue' => $tenant->vendor_environment === 'prod' ? 'prod' : 'stage',
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
            'AccountName' => (string) ($tenant->linnworks_email ?: 'Vendor Channel'),
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
