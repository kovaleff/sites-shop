import { vi } from 'vitest';

class ResizeObserverStub {
    observe() {}

    unobserve() {}

    disconnect() {}
}

globalThis.ResizeObserver = ResizeObserverStub;
window.ResizeObserver = ResizeObserverStub;

if (!window.matchMedia) {
    window.matchMedia = () => ({
        matches: false,
        media: '',
        onchange: null,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
        dispatchEvent: () => false,
    });
}

if (!Element.prototype.scrollIntoView) {
    Element.prototype.scrollIntoView = vi.fn();
}

if (!globalThis.PointerEvent) {
    globalThis.PointerEvent = MouseEvent;
}
