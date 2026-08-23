# Target Plus Channel for Linnworks

## User Manual

This guide is for Linnworks users who manage Target Plus products, listings, inventory, orders, and shipments. No technical knowledge is required.

> **Important:** The names and locations of Linnworks menu items can vary slightly depending on your Linnworks interface and permissions. If an option described here is not visible, ask your Linnworks administrator to confirm your access.

## What the Channel Does

The Target Plus Channel connects your Target Plus seller account to Linnworks. After it is installed and configured, it supports:

- Downloading Target Plus orders into Linnworks
- Displaying Target Plus products in Linnworks
- Linking Target Plus SKUs to Linnworks inventory items
- Creating and updating Target Plus listings
- Deactivating Target Plus listings
- Sending Linnworks inventory changes to Target Plus
- Sending shipment and tracking information to Target Plus

Some actions happen automatically on a schedule. Changes may not appear immediately.

## Before You Begin

Make sure you have:

- Access to the correct Linnworks account
- Permission to manage channel integrations and listings
- Your Target Plus seller ID
- Your Target Plus API key
- Your Target Plus seller token
- Confirmation of whether you are using a test/staging or production Target Plus account
- At least one product with the same recognizable SKU in Target Plus and Linnworks

Treat the Target Plus API key and seller token like passwords. Do not send them through email or include them in screenshots.

## 1. Install the Channel

Your Linnworks administrator may perform this step for you.

1. Open the Target Plus Channel installation link from Linnworks.
2. Review and approve the requested Linnworks access.
3. Wait for the Target Plus configuration page to open.
4. Do not close the page until the configuration has been saved successfully.

If you see an error opening the configuration page, contact your administrator. The integration address may be unavailable or may have changed.

## 2. Configure Your Target Plus Account

Enter the following values exactly as provided by Target Plus:

- **Seller ID:** Identifies your Target Plus seller account.
- **API key:** Allows the channel to communicate with Target Plus.
- **Seller token:** Authorizes access to your seller data.
- **Environment:** Select production only when you are ready to work with live Target Plus data.

Select **Save** or **Test and Save**, depending on the button displayed.

A successful test means the channel can reach Target Plus and recognize your credentials. It does not mean that every product is already linked or that every workflow has been tested.

### If the configuration test fails

Check that:

- No spaces were added before or after a credential.
- The seller ID belongs to the selected environment.
- The API key and seller token are current.
- You are connected to the internet.

If the values look correct, contact support. Do not repeatedly replace values unless Target Plus has issued new credentials.

## 3. Prepare Products and Link SKUs

Product linking is essential. It tells Linnworks which inventory item represents each Target Plus product.

The channel automatically retrieves the Target Plus catalog. A newly configured account may require up to an hour for a scheduled catalog refresh, although an administrator can request an immediate refresh.

### Link a Target Plus product

1. Open the channel or inventory mapping area in Linnworks.
2. Select the Target Plus Channel.
3. Refresh or download the channel products if required.
4. Find the Target Plus product by SKU or title.
5. Select the matching Linnworks inventory item.
6. Save the mapping.

Repeat this process for every product that will receive orders or inventory updates.

### A good SKU mapping

- Links the Target Plus product to the exact corresponding Linnworks item.
- Uses a non-empty Target Plus SKU.
- Does not link one Target Plus product to an unrelated variation or pack size.
- Is checked whenever a SKU is changed on either platform.

### Why mapping matters

Missing or incorrect mappings can cause:

- Order lines that Linnworks cannot identify
- Inventory updates being sent to the wrong product or not sent at all
- Listing changes affecting the wrong item
- Incorrect fulfillment decisions

## 4. Download Target Plus Orders

Orders follow this path:

1. The channel checks Target Plus for new or updated orders approximately every five minutes.
2. Linnworks runs its own external-channel order download, usually on a separate schedule.
3. Eligible orders appear in Linnworks.

Because two scheduled processes are involved, an order may not appear immediately. Allow approximately 15–20 minutes before treating a new order as missing.

### Set the Order Sync Date

The **Order Sync Date** tells Linnworks how far back it should request updated orders.

- For initial testing, set it shortly before the date and time of your test order.
- Do not move it far into the past without approval; this may request older orders again.
- Do not move it into the future; Linnworks may skip valid orders.

### Verify an imported order

Open the order in Linnworks and confirm:

- Target Plus order reference
- Customer and shipping address
- Product SKU and item
- Ordered quantity
- Item price, discounts, shipping, tax, and total
- Current order status

Do not process the order if the SKU, quantity, address, or total is incorrect. Record the order reference and contact support.

### If an order does not appear

Before contacting support:

1. Confirm the order was created or updated after the Order Sync Date.
2. Confirm the order is in a status that should be downloaded.
3. Wait at least 20 minutes.
4. Confirm the product SKU is mapped.
5. Check the Linnworks channel download history for a skipped-order or validation message.

## 5. Create a Target Plus Listing

Listing creation must be enabled for the channel. If no Target Plus configurator is visible, ask your Linnworks administrator to confirm that listing support and the required permissions are enabled.

### Create a listing

1. Open the Linnworks listing tool.
2. Select the inventory item you want to list.
3. Select the Target Plus Channel configurator.
4. Complete all required listing fields.
5. Review the SKU, title, price, category, and available quantity.
6. Submit the listing.
7. Monitor the listing feed or status until it reports success or an error.

Submitting a listing does not always mean it is immediately available for sale. Target Plus may need to validate the submitted product information.

### If a listing fails

Open the listing or feed result and note:

- Linnworks item number
- Target Plus SKU
- Listing or feed ID
- Error message
- Date and time of submission

Correct missing or invalid data and submit the listing again. Avoid creating a second listing for the same SKU unless the original listing is known not to exist.

## 6. Update or Deactivate a Listing

### Update a listing

1. Open the existing Target Plus listing in Linnworks.
2. Change the required information, such as price or product content.
3. Submit the update.
4. Monitor the feed result.
5. Confirm the existing Target Plus listing changed and that no duplicate listing was created.

### Deactivate a listing

Use the channel listing controls to deactivate or end the listing. After the request succeeds, confirm that the product is no longer available for sale on Target Plus.

Deactivation and deletion are not always the same:

- **Deactivate/end:** Stops the product from being sold while retaining its listing information.
- **Delete:** Removes the channel listing record where the platform permits it.

Use deactivation for normal temporary or permanent removal unless your business procedure specifically requires deletion.

## 7. Keep Inventory Synchronized

Linnworks is the operational source for available inventory after products have been mapped.

When available stock changes in Linnworks, Linnworks sends the new quantity to the Target Plus Channel. The channel then sends it to Target Plus.

### User responsibilities

- Keep each Target Plus SKU mapped to the correct Linnworks item.
- Confirm the correct Linnworks stock location is included in the channel configuration.
- Avoid changing the same inventory independently in both systems during testing.
- Investigate inventory errors promptly to reduce the risk of overselling.

### Verify inventory synchronization

1. Choose a mapped test item.
2. Record its current Target Plus quantity.
3. Change its available quantity in Linnworks.
4. Allow time for Linnworks to send the update.
5. Confirm Target Plus shows the expected quantity.

Use a small, controlled change during testing. Do not test with a high-volume live item unless the change represents real available stock.

### If inventory is not updating

Check that:

- The product is mapped.
- The SKU is unchanged and has no extra spaces.
- The correct stock location is selected.
- The item is active on the channel.
- Linnworks has attempted the inventory update.

Record the SKU, expected quantity, displayed quantity, and time of the change before contacting support.

## 8. Ship an Order and Send Tracking

Shipment information is sent when an imported Target Plus order is processed in Linnworks.

1. Open the Target Plus order in Linnworks.
2. Complete the normal pick, pack, and dispatch process.
3. Select the correct carrier and shipping service.
4. Enter the tracking number exactly as provided by the carrier.
5. Process or dispatch the order.
6. Confirm the order becomes shipped and tracking appears in Target Plus.

Do not enter placeholder tracking information for a live order.

### Before dispatching, verify

- The correct Target Plus order is open.
- The items and quantities being shipped are correct.
- The carrier and service are correct.
- The tracking number has no missing or extra characters.

If an order is only partially shipped, follow your organization's approved partial-shipment process. Partial shipment behavior must be validated before it is used with live Target Plus orders.

## 9. Cancellations and Refunds

The integration can send cancellation and refund information to Target Plus. These actions can affect live orders and customer payments.

- Confirm the correct order and items before cancelling or refunding.
- Follow your organization's approval process.
- Do not repeat the action simply because the Target Plus screen has not updated immediately.
- If the result is unclear, record the order reference and contact support before trying again.

Partial cancellations and partial refunds must be tested and approved before production use.

## 10. What Happens Automatically

Users do not need to manually start routine synchronization.

| Activity | Normal behavior |
|---|---|
| New and updated Target Plus orders | Channel checks approximately every 5 minutes; Linnworks downloads on its own cycle |
| Target Plus catalog | Refreshed approximately hourly |
| Channel inventory snapshot | Refreshed approximately every 30 minutes |
| Linnworks inventory changes | Sent by Linnworks when stock changes |
| Linnworks connection renewal | Refreshed automatically approximately every 30 minutes |
| Cancellations waiting to be sent | Checked approximately every 15 minutes |
| Old synchronization history | Cleaned up automatically after the retention period |

Scheduled timing is approximate. An active process may finish after its scheduled start time.

## 11. Daily Operational Checks

The person responsible for the channel should check:

- New Target Plus orders are arriving in Linnworks.
- No orders are repeatedly skipped or rejected.
- Listings submitted recently completed successfully.
- Inventory errors are not accumulating.
- Recently dispatched orders show tracking in Target Plus.
- Credentials have not expired or been replaced.

For high-volume operations, use automated alerts and a daily exception report instead of manually checking every successful transaction.

## 12. When to Contact Support

Contact support when:

- Configuration fails with credentials that Target Plus confirms are valid.
- A valid order is missing after 20 minutes.
- An order is imported with incorrect customer, item, quantity, or total information.
- A mapped item repeatedly fails inventory updates.
- A listing remains pending or fails without an understandable correction.
- Shipment or tracking is not visible in Target Plus.
- A cancellation or refund has an uncertain result.
- The channel configuration page will not open.

### Information to include

Provide:

- Linnworks account or channel name
- Target Plus seller ID — never include the seller token or API key
- Order reference, SKU, or listing/feed ID
- Date and time of the action, including time zone
- Expected result and actual result
- Full error message
- Screenshot with customer details and credentials hidden
- Correlation ID, if one is displayed or provided in the logs

Never send API keys, seller tokens, passwords, payment information, or unredacted customer data in a support message.

## 13. Removing the Channel

Removing a channel stops future synchronization. It does not necessarily reverse listings, inventory updates, shipments, cancellations, or refunds already sent to Target Plus.

Before removing the channel:

1. Confirm all open Target Plus orders have an owner and fulfillment plan.
2. Decide whether active listings should remain available or be deactivated.
3. Record any pending listing, inventory, shipment, cancellation, or refund errors.
4. Ask an administrator to remove the channel configuration from Linnworks.
5. Confirm that automated synchronization has stopped.

Deleting the Linnworks application itself may not notify the integration. Ask support or an administrator to confirm that the seller connection has also been disabled in the integration.

## Go-Live Checklist

Do not use the channel for live orders until all items below are complete:

- [ ] Target Plus configuration test succeeds.
- [ ] Target Plus products appear in Linnworks.
- [ ] All sellable SKUs are mapped correctly.
- [ ] A test order imports with correct details.
- [ ] A test listing is created successfully.
- [ ] The listing can be updated and deactivated without duplication.
- [ ] A Linnworks inventory change reaches the correct Target Plus SKU.
- [ ] A test shipment reaches Target Plus with correct tracking.
- [ ] Cancellation and refund procedures are understood and approved.
- [ ] Partial shipment/cancellation/refund requirements have been tested or declared out of scope.
- [ ] Monitoring and support contacts are in place.
- [ ] Production credentials are stored securely.

The companion Draw.io workflow and acceptance checklist is available in `docs/target-plus-user-workflow.drawio`.
