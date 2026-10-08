# Review the rendered result

Use a browser to inspect the page you actually changed. Separate what you observed from what you inferred from stored content. If browser inspection is unavailable, report that visual and interaction verification remains incomplete. This checklist is a practical review, not a complete accessibility audit.

## Check a representative range

Inspect desktop around 1440px wide, an intermediate width around 768px, and mobile around 390px as useful starting sizes. Also test a 320 CSS-pixel-wide viewport for reflow; normal page content should not require horizontal scrolling. Essential two-dimensional content, such as some data tables, may need its own scroll region. These sizes are test samples, not required breakpoints. [Reflow reference](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html).

Check a short and a long title, an image-heavy item and one with limited imagery. Enlarge text to 200%, test keyboard navigation, and inspect reduced-motion behavior if the page animates. Look at the public view as well as the signed-in view when possible.

## Inspect in this order

1. **Purpose and hierarchy.** Can a new visitor tell what the site offers and where to go next? Is the most important content prominent? Does each section add something? Are claims and project details real?
2. **Composition.** Check alignment, whitespace, type relationships, headline wrapping, image crops and transitions between sections. Look at the whole page and close detail; a strong hero cannot compensate for an unfinished lower page.
3. **Reading and access.** Measure contrast; inspect heading order, meaningful image alternatives, labels and focus visibility. Ensure focused controls are not covered by sticky UI. Make targets comfortable: roughly 44px is a useful design starting point. WCAG 2.2 AA specifies a 24-by-24 CSS-pixel minimum with defined spacing and other exceptions; inspect small controls in context. [Target-size reference](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html).
4. **Behavior.** Follow the main action, navigation and project links. Open and close the mobile menu with touch and keyboard. For forms, verify labels, validation, error recovery and success feedback using appropriate test data. Do not send a real inquiry or trigger an external transaction solely to test design without authorization.
5. **Delivery and editing.** Confirm images and fonts load, important media is not needlessly oversized, and the layout does not jump visibly during loading. Read back the saved values. Where relevant, reopen the editor and confirm the intended editing controls still work.

## Fix and report

Treat broken navigation, hidden actions, unreadable text, missing media and accidental overflow as blockers. Address weaker hierarchy, generic repetition and uneven spacing next. Polish effects last. Describe a defect precisely: location, viewport/state, observed problem and intended correction. Change the underlying shared decision when the defect repeats.

After fixing, revisit the affected state and one other use of the shared component. Stop when the brief and concrete acceptance checks are satisfied; endless aesthetic variation is not evidence of improvement.

Report the chosen direction, key changes, URLs and states actually checked, and unresolved asset or verification gaps. A screenshot establishes appearance at one moment; it does not establish working navigation, accessibility conformance or performance.
