=== MAC Frontend Motion Editor ===
Contributors: mac
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 0.2.1
License: GPL-2.0-or-later

Edit native Elementor entrance animations on the frontend.

== Installation ==
Upload the mac-frontend-motion-editor ZIP in Plugins > Add New > Upload Plugin.
Activate Elementor, then activate this plugin.
Open Tools > MAC Frontend Motion, enable editing for your account, and save.
Visit an Elementor page you can edit. Hover an element and click Motion.

== Editing ==
Replay previews in the browser without saving. Apply saves through Elementor's
document lifecycle. Device tabs edit animation overrides, not viewport size.
Speed and delay are shared across devices. Default/Clear removes the selected
device override and resets shared speed/delay. Other device animations remain.
None explicitly disables animation on the selected device. A cleared responsive
override may inherit the animation from a larger device.
Undo restores the previous motion values, including shared speed/delay. History
exists only in the current browser session and is lost on reload.
Select parent lets you animate a group. Escape or Close restores preview classes.
Turn off stops loading editor assets; saved native animations remain active.

== GitHub updates ==
Install the release ZIP once (replace an existing 0.2.0 installation once).
After a push to main, GitHub Actions tests and publishes a new versioned release.
In Plugins, click Check updates then Update now. WordPress also checks on its
normal schedule. No GitHub token is required. Download the named plugin ZIP from
Releases, not the repository Source code ZIP. A failed workflow publishes nothing.

== Boundaries ==
Only the main singular page/post document is editable. Shared templates, global
widgets, loop items and Atomic/V4 e-* widgets are locked. Elementor editor and
preview sessions do not load the tool. Third-party animation providers must
register their own CSS; the tool reads Elementor's animation catalogue.
If the page changed since loading, save returns a conflict and asks for reload.
The hash check is optimistic; avoid concurrent editing during a save.

== Development and verification ==
The repository-root SPEC.md is the canonical V1 specification. The plugin's copy
is kept synchronized. Earlier unused code/specs are preserved under archive/.
Run npm ci, npx playwright install chromium, then npm test at the repository root
for static PHP 7.4 parsing and browser tests. These tests mock the save response;
they do not establish
that Elementor persists data correctly. With PHP available, also run
php tests/backend.php for isolated backend contract tests.

Before rollout on a staging WordPress + Elementor site:
1. Apply fadeInUp / Fast / 200 ms to a heading and fadeIn to a container.
2. Reload, then open Elementor > Advanced > Motion Effects and check values.
3. Set tablet/mobile only; confirm desktop unchanged and responsive inheritance.
4. Clear and confirm keys removed; Undo and confirm settings restored.
5. Save in another tab, then Apply here: expect conflict with no overwrite.
6. Check shared templates and V4 widgets remain locked, including colliding IDs.
7. Turn editing off: editor assets absent and saved animation still runs.
8. Save again in Elementor and test export/import retains native motion values.
9. Check another account and a logged-out visitor never receive editor assets.

== Changelog ==
= 0.2.1 =
Added native WordPress update checks backed by tested GitHub release packages.

= 0.2.0 =
Completed frontend inspector, preview, native save, responsive animation settings,
Clear, Undo, per-user toggles, conflict handling and motion guidance.
