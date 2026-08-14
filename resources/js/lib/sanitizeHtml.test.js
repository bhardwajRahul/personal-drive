import { test } from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import createDOMPurify from "dompurify";
import { marked } from "marked";
import { createSanitizeHtml } from "./sanitizeHtml.js";

// Build a jsdom-backed DOMPurify instance so DOMPurify can run under Node.
const { window } = new JSDOM("");
const DOMPurify = createDOMPurify(window);
const sanitizeHtml = createSanitizeHtml(DOMPurify);

test("sanitizeHtml removes <script> tags", () => {
    const result = sanitizeHtml("<script>alert(1)</script>");
    assert.ok(!result.includes("script"));
    assert.ok(!result.includes("alert(1)"));
});

test("sanitizeHtml keeps <img> but drops event-handler attributes", () => {
    const result = sanitizeHtml('<img src="x" onerror="alert(1)">');
    assert.match(result, /<img/);
    assert.ok(result.includes('src="x"'));
    assert.ok(!result.includes("onerror"));
});

test("sanitizeHtml removes javascript: href from <a>", () => {
    const result = sanitizeHtml('<a href="javascript:alert(1)">click</a>');
    assert.match(result, /<a[^>]*>click<\/a>/);
    assert.ok(!result.includes("javascript:"));
});

test("sanitizeHtml sanitizes marked output, keeping markdown but stripping scripts", () => {
    const rendered = marked.parse("# hi\n<script>x</script>");
    const result = sanitizeHtml(rendered);
    assert.match(result, /<h1>hi<\/h1>/);
    assert.ok(!result.includes("<script"));
});
