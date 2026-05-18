import { useBlockProps } from "@wordpress/block-editor";

export default function save({ attributes }) {
	const {
		heading,
		text,
		imageUrl,
		linkUrl,
		linkTarget,
		overlayOpacity,
		overlayHoverOpacity,
		overlayColor,
	} = attributes;

	return (
		<div
			{...useBlockProps.save({
				className: "clickable-cover-card",
				style: {
					"--overlay-opacity": `${overlayOpacity}`,
					"--hover-overlay-opacity": `${overlayHoverOpacity}`,
					"--overlay-color": overlayColor || "#000000",
					backgroundImage: imageUrl ? `url(${imageUrl})` : undefined,
					backgroundSize: "cover",
					backgroundPosition: "center",
				},
			})}
			data-hover-opacity={overlayHoverOpacity}
		>
			<div className="overlay" />
			<div className="cover-content">
				<h3>{heading}</h3>
				<p>{text}</p>
			</div>
			<a
				className="cover-link"
				href={linkUrl}
				target={linkTarget}
				rel="noopener noreferrer"
				aria-label={heading}
			/>
		</div>
	);
}
