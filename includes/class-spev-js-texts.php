<?php
/**
 * Translation anchors for the strings the JavaScript bundle shows.
 *
 * ⚠️ **GENERATED FILE - DO NOT EDIT.** Produced by `_claude-tools/js-texts-to-php.php` from the
 * `t( '…' )` calls in the JavaScript source. Change the text in the JavaScript source and run the
 * tool again; editing here would create a second English original that silently drifts away from the
 * one the browser looks up.
 *
 * ⚠️ **NOTHING CALLS THIS METHOD, AND THAT IS THE POINT.** The browser does not read these strings
 * from here - it loads them from `i18n/<lang>.json.js`, which the build generates from this
 * plugin\'s own `.po`. They exist so a PHP text scanner ( `wp i18n make-pot`,
 * `_claude-tools/makepot.php` ) can see them, because no scanner reads JavaScript, and the `.pot`
 * is the file translate.wordpress.org offers to translators. Without this file every string that
 * only the browser shows would be invisible there.
 *
 * ⚠️ **THE LOOP IS CLOSED, WHICH IT WAS NOT BEFORE.** A translator edits the string on
 * translate.wordpress.org, it arrives in the `.po`, `_claude-tools/i18n-build.php` turns the `.po`
 * into the table the browser loads, and the browser shows it. An earlier version of this file was
 * withdrawn on the grounds that the browser never reads what PHP registers - true in itself, and
 * the wrong conclusion, because the path does not run through PHP at runtime but through the `.po`
 * at build time.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) )
	exit;


class SPEV_Js_Texts
{

	/**
	 * The 120 strings of the JavaScript user interface, each with its source file named above it.
	 *
	 * The array is returned rather than assigned to a variable nobody reads, so no code checker has to
	 * decide whether an unused local is a mistake.
	 *
	 * @return string[]
	 */

	public static function anchors(): array
	{

		return array(
			// require-api.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( '%1$s needs the eInvoice Viewer %2$s or later.', 'einvoice-viewer-for-peppol-en16931' ),
			// ModelAssembler.mjs
			__( 'Account holder', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Address', 'einvoice-viewer-for-peppol-en16931' ),
			// ModelAssembler.mjs
			__( 'Allowance', 'einvoice-viewer-for-peppol-en16931' ),
			// ModelAssembler.mjs
			__( 'Allowances & charges', 'einvoice-viewer-for-peppol-en16931' ),
			// QubaTemplate.mjs
			__( 'Amount due', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'Attachment description', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'Attachment file name', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'Attachment format', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'Attachments', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Buyer tax representative', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Cash discount / penalty terms', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'Cash discount, term %d', 'einvoice-viewer-for-peppol-en16931' ),
			// ModelAssembler.mjs
			__( 'Charge', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Close', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Contact', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Copy', 'einvoice-viewer-for-peppol-en16931' ),
			// QubaTemplate.mjs
			__( 'Currency', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Delivery note date', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Delivery note reference', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Document flags', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Drag an e-invoice here or click to choose a file', 'einvoice-viewer-for-peppol-en16931' ),
			// Codelists.mjs
			__( 'Electronic mail', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Further agreement data', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Further delivery data', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Further fields', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Further references', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Further settlement data', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs, svze-extend.js
			__( 'Identifier', 'einvoice-viewer-for-peppol-en16931' ),
			// QubaTemplate.mjs
			__( 'Invoice date', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Invoicee', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Invoicer', 'einvoice-viewer-for-peppol-en16931' ),
			// ModelAssembler.mjs
			__( 'Item', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Language', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Legal organisation', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Load from file', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Loading the preview …', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Logistics service charge', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Logistics service charge amount', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'Missing, although EN 16931 requires this field here (%s).', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'Missing: EN 16931 requires a reason or a reason code here (%s).', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'Missing: with VAT category %1$s, EN 16931 requires the buyer VAT identifier (%2$s).', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'Missing: with VAT category %1$s, EN 16931 requires the buyer VAT identifier or the buyer legal registration identifier (%2$s).', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'Missing: with VAT category %1$s, EN 16931 requires the seller VAT identifier or the tax representative VAT identifier (%2$s).', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'Missing: with VAT category %1$s, EN 16931 requires the seller VAT identifier, the seller tax registration identifier or the tax representative VAT identifier (%2$s).', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Name', 'einvoice-viewer-for-peppol-en16931' ),
			// ModelAssembler.mjs
			__( 'No liability is assumed for the accuracy of the data.', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			__( 'Note: this is not a full validation. The viewer only checks mandatory fields, the VAT category rules, the order of the dates and whether the totals add up. A validator checks all rules.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Only filled BT-X fields', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Only filled fields', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'Only the first %1$d of %2$d pages are shown. Save the file to see all of them.', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'Penalty, term %d', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Product end user', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Quotation date', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Quotation reference', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Reading the invoice …', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'Reference (URL)', 'einvoice-viewer-for-peppol-en16931' ),
			// Codelists.mjs
			__( 'Related document', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Replace', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Reset', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs, einvoice-viewer.mjs
			__( 'Save', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Save as file', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Saved.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Settings', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Ship from', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Show BT codes', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'Size', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Somebody else has saved these settings in the meantime. Close the dialog and open it again to see their version.', 'einvoice-viewer-for-peppol-en16931' ),
			// ModelAssembler.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'Stated %1$s, calculated %2$s.', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'Sub invoice lines of item %s', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'Sub line %s', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Tax registration', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Test document', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'The CII field catalog could not be loaded.', 'einvoice-viewer-for-peppol-en16931' ),
			// app.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'The Extended Profiles add-on is in the folder, but its field package for %s could not be loaded. The core fields are shown.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'The UBL field catalog could not be loaded.', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'The attachment could not be decoded.', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'The attachment could not be shown.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'The backup is loaded.', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'The content of this attachment does not match its declared file type. It is neither opened nor offered for saving.', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'The end date is before the start date (%s).', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'The file could not be read.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'The file is too large.', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			__( 'The payment due date is before the invoice date.', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'The preview needs the PDF reader, which could not be loaded. The file can still be saved.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'The settings could not be saved.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'These sections will be replaced as a whole:', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Third party payment amount', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Third party payment description', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Third party payment total', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Third party payment type', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Third party payments', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'This PDF carries no invoice XML. A scanned or ordinary invoice PDF holds no data that can be read.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'This backup holds no settings.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'This file is not a settings backup.', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'This file type is not allowed in an e-invoice. It is neither opened nor offered for saving.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'This file was written by a newer version and cannot be loaded here.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'This invoice cannot be shown at the moment.', 'einvoice-viewer-for-peppol-en16931' ),
			// app.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'This invoice uses the extension %s. All core fields are shown; the additional fields of the extension come with the Extended Profiles add-on.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'This section could not be shown.', 'einvoice-viewer-for-peppol-en16931' ),
			// ModelAssembler.mjs
			__( 'Totals', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'Type of referenced document', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Ultimate customer order date', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Ultimate customer order reference', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Ultimate ship to', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Unsupported file. Please upload a ZUGFeRD/Factur-X PDF or a CII / UBL XML invoice.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'Upload an e-invoice', 'einvoice-viewer-for-peppol-en16931' ),
			// ModelAssembler.mjs
			__( 'VAT breakdown', 'einvoice-viewer-for-peppol-en16931' ),
			// Attachments.mjs
			__( 'View', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'With VAT category %1$s the VAT rate must be 0 (%2$s).', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'With VAT category %1$s the VAT rate must be greater than zero (%2$s).', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'With VAT category %1$s the tax amount must be 0 (%2$s).', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'With VAT category %1$s there must be no exemption reason and no exemption reason code (%2$s).', 'einvoice-viewer-for-peppol-en16931' ),
			// InvoiceHints.mjs
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'With VAT category %1$s, EN 16931 requires an exemption reason or an exemption reason code (%2$s).', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			__( 'Yes', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'You may look at these settings, but not change them.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'You may not change these settings.', 'einvoice-viewer-for-peppol-en16931' ),
			// einvoice-viewer.mjs
			__( 'ZUGFeRD / Factur-X PDF or CII / UBL XML', 'einvoice-viewer-for-peppol-en16931' ),
			// svze-extend.js
			/* translators: the placeholders are values the JavaScript fills in at run time; see the source file named above. */
			__( 'by %s', 'einvoice-viewer-for-peppol-en16931' ),
			// ModelAssembler.mjs
			__( 'yes', 'einvoice-viewer-for-peppol-en16931' ),
		);

	}

}
