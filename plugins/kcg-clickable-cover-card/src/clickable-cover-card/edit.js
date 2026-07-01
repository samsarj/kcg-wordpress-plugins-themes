import {
	useBlockProps,
	InspectorControls,
	PanelColorSettings,
	MediaUpload,
	__experimentalLinkControl as LinkControl,
} from "@wordpress/block-editor";
import {
	PanelBody,
	RangeControl,
	TextControl,
	Button,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import "./editor.scss";

export default function Edit({ attributes, setAttributes }) {
	const {
		heading,
		text,
		imageUrl,
		linkUrl,
		linkTarget,
		overlayColor,
		overlayOpacity,
		overlayHoverOpacity,
	} = attributes;

	const blockProps = useBlockProps({
		style: {
			"--overlay-opacity": `${overlayOpacity}`,
			"--hover-overlay-opacity": `${overlayHoverOpacity}`,
			"--overlay-color": overlayColor || "#000000",
			backgroundImage: imageUrl ? `url(${imageUrl})` : undefined,
			backgroundSize: "cover",
			backgroundPosition: "center",
		},

		className: "clickable-cover-card",
	});

	return (
		<>
			<InspectorControls>
				<PanelBody title="Content">
					<TextControl
						label="Heading Text"
						value={heading}
						onChange={(val) => setAttributes({ heading: val })}
					/>
					<TextControl
						label="Paragraph Text"
						value={text}
						onChange={(val) => setAttributes({ text: val })}
					/>
					<LinkControl
						value={{
							url: linkUrl || "",
							opensInNewTab: linkTarget === "_blank",
						}}
						onChange={({ url, opensInNewTab }) =>
							setAttributes({
								linkUrl: url,
								linkTarget: opensInNewTab ? "_blank" : "_self",
							})
						}
						withCreateSuggestion={false}
						forceIsEditingLink={true}
					/>
				</PanelBody>
				<PanelBody title="Background Image">
					<MediaUpload
						onSelect={(media) => setAttributes({ imageUrl: media.url })}
						type="image"
						value={imageUrl}
						render={({ open }) => (
							<Button onClick={open} isSecondary>
								{imageUrl ? "Change Image" : "Select Background Image"}
							</Button>
						)}
					/>
				</PanelBody>
				<PanelBody title="Text & Overlay">
					<PanelColorSettings
						initialOpen={true}
						colorSettings={[
							{
								value: attributes.overlayColor,
								onChange: (color) => setAttributes({ overlayColor: color }),
								label: "Overlay Color",
							},
						]}
					/>
					<RangeControl
						label="Overlay Opacity"
						value={overlayOpacity}
						onChange={(val) => setAttributes({ overlayOpacity: val })}
						min={0}
						max={1}
						step={0.05}
					/>
					<RangeControl
						label="Hover Overlay Opacity"
						value={overlayHoverOpacity}
						onChange={(val) => setAttributes({ overlayHoverOpacity: val })}
						min={0}
						max={1}
						step={0.05}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="overlay" />
				<a className="cover-link" href={linkUrl} aria-label={heading} />
				<div className="cover-content">
					<h3>{heading}</h3>
					<p>{text}</p>
				</div>
			</div>
		</>
	);
}
