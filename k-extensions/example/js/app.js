// Plain ES module -- no bundler, no build step. Linked onto the page via
// Extension::extendsPortals()'s ->js() call whenever the current request resolves to the
// Portal it's attached to.
console.log('example extension loaded');
