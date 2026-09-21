// Exposes jQuery as window.$/window.jQuery, in its own module so that
// assignment actually happens before select2/datatables.net-bs5 import —
// a plain statement sitting inline in app.js after `import jQuery from
// 'jquery'` runs too late: ES module imports are hoisted and evaluated
// before any of that file's own top-level statements, so a sibling
// `import 'select2'` declared afterward still sees window.jQuery unset at
// the moment select2 tries to attach itself to it. Moving the assignment
// into its own module (mirroring bootstrap.js's window.axios pattern)
// makes it part of *this* import's resolution, which completes before the
// next sibling import starts.
import jQuery from 'jquery';
window.$ = window.jQuery = jQuery;
