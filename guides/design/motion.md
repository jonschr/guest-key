# Make motion serve the design

Choose a motion character alongside typography, color and composition. A precise service site may use immediate, restrained feedback. An expressive portfolio may use a more deliberate image transition. Neither needs animation on every section. Consistent timing and easing give a site more character than unrelated effects.

## Assign a job

Use motion to confirm an action, explain a change of state, connect related elements or establish a deliberate focal moment. Examples: a menu's open state, a button's pressed feedback, a project image transitioning into its detail view. Identify what the visitor should understand better because the element moves. Remove an effect when that answer is unclear.

As starting points, try roughly 120–240ms for small control feedback and 250–450ms for larger transitions, then inspect them on the actual interface. These are taste decisions, not standards. Choose easing that settles naturally; avoid excessive bounce for ordinary navigation. Define shared motion values rather than choosing new timings for each component.

## Keep the interface available

Keep normal navigation and scrolling predictable. Do not make visitors wait through a logo sequence, scroll through an artificial timeline to reach content, or hover to reveal essential information. A transition must not delay the underlying action or leave focus on an invisible control. Ensure controls still work during rapid repeated input.

For decorative movement, prefer transform and opacity where suitable, and inspect smoothness on a smaller device. Reserve layout space before images or animated content load. Avoid continuous background motion competing with reading. Never use flashing as visual emphasis.

## Offer a quiet version

Respect the user's reduced-motion preference with the implementation's supported controls or `prefers-reduced-motion`. Remove decorative movement and spatial transitions, preserving the final state and useful feedback. Keep content visible if animation scripts fail. Autoplay media needs appropriate playback controls; do not autoplay sound to set a mood.

In WordPress, inspect the theme or builder's actual animation controls before applying effects. Test entrance animations, mobile menus and overlays on the frontend, including reduced motion and keyboard focus. Choose a simpler working interaction when the available editor cannot maintain the intended behavior reliably.
