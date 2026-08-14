import DOMPurify from "dompurify";

/**
 * Create a sanitize function bound to a specific DOMPurify instance.
 * Defaults to the browser DOMPurify instance; tests pass a jsdom-backed one.
 */
export function createSanitizeHtml(purify = DOMPurify) {
    return (html) => purify.sanitize(html);
}

/**
 * Sanitize untrusted HTML (e.g. rendered markdown) with DOMPurify's default
 * config, which strips <script> tags, event-handler attributes, and
 * javascript: URLs.
 */
export const sanitizeHtml = createSanitizeHtml();
