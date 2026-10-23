const installed = new WeakSet();

// Popstate targets Window: Chromium invokes same-target listeners in registration order,
// even when a later listener requests capture. Register before Inertia initializes.
export function installTraversalDispatch(win) {
    if (installed.has(win)) return;
    installed.add(win);
    win.addEventListener('popstate', event => {
        const traversal = new win.Event('zafira:dashboard-traversal', { cancelable: true });
        if (!win.dispatchEvent(traversal)) event.stopImmediatePropagation();
    }, true);
}

export function onDashboardTraversal(win, handler) {
    installTraversalDispatch(win);
    win.addEventListener('zafira:dashboard-traversal', handler);
    return () => win.removeEventListener('zafira:dashboard-traversal', handler);
}
