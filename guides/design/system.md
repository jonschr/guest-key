# Define a small, coherent visual system

Read the existing presets first. Keep a compact set of named decisions for type, colors, spacing, content widths, borders and component states. Reuse them across pages. The values below are design starting points, not accessibility rules or universal requirements.

## Typography

Choose type for the subject and the content it must carry. An expressive display face can give headings character; body text needs comfortable reading at actual sizes. One family with useful weights may be enough. Add a second only when the contrast serves a purpose. Check that available fonts include the needed characters and weights, and avoid unnecessary downloads.

Define roles for page title, section heading, body, caption and label. Start body copy around 1rem–1.125rem with line height around 1.5–1.7; inspect it with the actual font. Keep long reading roughly 45–75 characters per line as a starting range. Navigation and captions must remain readable too. Test the longest heading on mobile; use flexible sizes and natural wrapping rather than inserting line breaks to fit one screenshot.

Make hierarchy visible through size, weight, spacing and position together. Use heading levels for document structure; a visually small heading may still be an h2. Treat tracking and uppercase as intentional accents, especially in long labels. Do not reject a font merely because it is common; judge whether the chosen combination has character and works here.

## Color and surfaces

Assign roles: canvas, surface, primary text, secondary text, accent, border, success and error. Start with a controlled palette that suits the brand and images. A vivid palette can work when its roles are consistent. Repeated arbitrary colors weaken the system.

Measure text contrast against its actual background: aim for at least 4.5:1 for normal text; the WCAG large-text threshold permits 3:1. Test links, captions, placeholders, hover states and image overlays, not just the headline. Do not communicate errors or selected states through color alone. Photographs change contrast across the crop; use a dependable surface or treatment behind text when necessary. [Text contrast reference](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).

## Spacing and components

Choose a short spacing scale, for example 4, 8, 12, 16, 24, 32, 48 and 64 CSS pixels expressed through supported tokens. Use close spacing to group related information and larger spacing to separate ideas. Different section spacing can establish pace, but unexplained one-off values create drift.

Define a few repeated treatments: buttons, links, project captions, form fields and any recurring cards. Set their normal, hover, focus, active, disabled and error states where relevant. Use consistent corner and border treatment unless a specific component calls for a different role. A card, shadow, gradient or icon should clarify grouping or meaning; empty decoration is a poor substitute for strong type and imagery.

Record the chosen roles and values in the working note. Fix the shared decision when a repeated component looks wrong, then inspect its other uses before applying an exception.
