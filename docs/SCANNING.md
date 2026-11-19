# Optional equipment QR scanning

Staff can mix scanned and manually selected equipment in the same borrowing transaction. The existing manual equipment dropdown and Add item control remain available. Scanning happens on the signed-in device, inside Record borrowing; there is no second-device pairing or automatic loan submission.

## Borrowing flow

1. Open Record borrowing and select Scan QR beside Equipment to lend.
2. Choose Camera, then Start camera, or choose Scanner / asset tag.
3. A successful lookup adds the equipment at quantity one, filling an empty row first. The scanner shows its name, asset tag and available quantity.
4. Scan another item or close the dialog. Review quantities in the normal form, enter borrower details, then record the loan.

Camera permission is requested only after Start camera. The rear camera is preferred when available; Switch camera appears when multiple cameras are detected. Camera tracks and the decoder are destroyed after a read, on dialog closure, mode change, page navigation, tab hiding and component destruction. Permission/device errors explain how to continue with a scanner, asset tag, or manual selection.

USB scanners must operate as keyboard input. Focus Equipment code or asset tag and scan; Enter adds the item without submitting the borrowing form. Typed asset tags also work. Repeated scans flag the existing selection and never increment its quantity. Batches default to one unit; staff adjust quantity in the borrowing form. Individually tracked assets remain quantity one under the existing inventory rules.

The dialog is responsive, keyboard accessible and uses the existing teal palette, Flux controls and Poppins type. Scan results are workflow feedback inside the dialog; saved borrowing confirmations continue through the shared Toastr handler.

## QR labels

Equipment details now has a QR label action, available to authorized inventory users. It generates a high-contrast black/white label locally, with Print label and Download QR controls. Printing includes the equipment name and asset tag; the download contains the QR image. Printing may require allowing the browser popup. No camera frames or label images are uploaded to an external service.

The encoded value is `CLIMS:equipment:<record-id>`. It identifies one equipment record and remains valid when that record's asset tag or name changes. It contains no user credentials, borrower information or authorization token. Asset tags are also accepted as scanner input. Website URLs and malformed CLIMS payloads are rejected rather than opened.

## Server behavior

`Equipment::qrPayload()` supplies the label value. The borrowing page's `addScannedEquipment()` action refreshes the actor, checks borrowing and equipment-view authorization, resolves the equipment and checks current availability. Missing/unavailable records and the 50-line limit receive explicit feedback. It does not change stock, create borrowing records, or generate misleading audit events.

Saving still uses `ManageBorrowing` validation, database transactions and row locks. A selection made while stock was available can be rejected later if another loan consumes that stock. Frontend scan values never determine permissions or inventory calculations.

## Packages and deployment

`qr-scanner` supplies local camera decoding and its fallback worker; `qrcode` generates labels. Both are installed through npm, lazy-loaded, and bundled locally by Vite. No CDN, PHP package or schema change is required.

Camera access requires a secure browser context: use HTTPS on the deployed site and when opening it from phones over the school network. Localhost is suitable for local development; a plain HTTP LAN address does not provide camera access. Manual selection and keyboard-scanner/asset-tag entry remain available. See [MDN camera access requirements](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia) and the [QR scanner documentation](https://github.com/nimiq/qr-scanner).

## Validation

Feature tests cover mixed manual/scanned rows, borrower-data preservation, default quantity, duplicate detection, unavailable/missing/invalid codes, unrelated URLs, immutable QR identities, line limits, deactivation and stock changes before final save. No live inventory was used during browser review.

Browser checks used an isolated temporary database and covered mobile layout, asset-tag lookup, duplicate feedback, mixed-entry loan creation, generated-label decoding, permission-error guidance and camera decoding with synthetic video. A separate live synthetic stream confirmed that closing the modal ends its tracks and clears the video element. Real phone/webcam and physical printed-label checks remain part of device acceptance testing.

This iteration does not add scanning to returns, bulk label printing or automatic quantity increments.
