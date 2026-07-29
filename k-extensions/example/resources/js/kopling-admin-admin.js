// Illustrative only -- a second, distinct bundle from resources/js/app.js, proving
// PortalExtension::compiledAssets() resolves a different file per Portal (auto-derived from
// "kopling-admin::admin", sanitized) rather than the two Portal attachments in Extension.php
// colliding on one shared app.js. See CLAUDE.md's "How extensions ship compiled assets".
console.log('kopling-example: admin-only bundle loaded');
