/**
 * OpenProvider WooCommerce - Domain Search Block (Editor)
 */
import { registerBlockType } from '@wordpress/blocks';
import { PanelBody, FormTokenField, TextControl } from '@wordpress/components';
import { InspectorControls } from '@wordpress/block-editor';

registerBlockType('openprovider-woocommerce/domain-search', {
	edit: function Edit({ attributes, setAttributes }) {
		const { defaultTlds, buttonLabel } = attributes;

		return (
			<>
				<InspectorControls>
					<PanelBody title="Settings" initialOpen={true}>
						<FormTokenField
							label="Default TLDs"
							value={defaultTlds}
							onChange={(value) => setAttributes({ defaultTlds: value })}
							description="Comma-separated list of TLDs to show by default"
						/>
						<TextControl
							label="Button Label"
							value={buttonLabel}
							onChange={(value) => setAttributes({ buttonLabel: value })}
						/>
					</PanelBody>
				</InspectorControls>
				<div style={{ padding: '20px', textAlign: 'center', border: '1px dashed #ccc', borderRadius: '4px' }}>
					<h3>Domain Search Block</h3>
					<p>Configure TLDs and button label in the settings panel.</p>
					<p><em>Preview will appear on the frontend.</em></p>
				</div>
			</>
		);
	},
	save: function Save() {
		return null; // Dynamic block - renders server-side
	},
});
