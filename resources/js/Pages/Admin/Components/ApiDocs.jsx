import EndpointTable from "./EndpointTable";

const FILE_ENDPOINTS = [
    { method: "GET", url: "/api/v1/files", description: "List files (paginated)", params: "path, per_page" },
    { method: "GET", url: "/api/v1/files/:id", description: "Get file info", params: "" },
    { method: "POST", url: "/api/v1/files/upload", description: "Upload files", params: "files[], path" },
    { method: "POST", url: "/api/v1/files/create", description: "Create file/folder", params: "name, type, path" },
    { method: "GET", url: "/api/v1/files/:id/download", description: "Download file", params: "" },
    { method: "DELETE", url: "/api/v1/files/:id", description: "Delete file", params: "" },
    { method: "POST", url: "/api/v1/files/move", description: "Move files", params: "fileList[], destination" },
    { method: "POST", url: "/api/v1/files/:id/rename", description: "Rename file", params: "name" },
    { method: "POST", url: "/api/v1/files/:id/save", description: "Save file content", params: "content" },
];

const SEARCH_ENDPOINTS = [
    { method: "GET", url: "/api/v1/search?q=...", description: "Search files (paginated)", params: "q" },
];

const FAVORITE_ENDPOINTS = [
    { method: "GET", url: "/api/v1/favorites", description: "List favorites (paginated)", params: "" },
    { method: "POST", url: "/api/v1/favorites", description: "Add favorite", params: "local_file_ids[]" },
    { method: "DELETE", url: "/api/v1/favorites/:id", description: "Remove favorite", params: "" },
];

const SHARE_ENDPOINTS = [
    { method: "GET", url: "/api/v1/shares", description: "List shares (paginated)", params: "" },
    { method: "POST", url: "/api/v1/shares", description: "Create share", params: "fileList[], slug?, password?, expiry?" },
    { method: "DELETE", url: "/api/v1/shares/:id", description: "Delete share", params: "" },
    { method: "POST", url: "/api/v1/shares/:id/toggle", description: "Toggle share enabled/disabled", params: "" },
];

const SECTIONS = [
    { title: "Files", endpoints: FILE_ENDPOINTS },
    { title: "Search", endpoints: SEARCH_ENDPOINTS },
    { title: "Favorites", endpoints: FAVORITE_ENDPOINTS },
    { title: "Shares", endpoints: SHARE_ENDPOINTS },
];

export default function ApiDocs() {
    return (
        <div className="bg-slate-900/50 p-4 md:p-6 rounded-lg border border-blue-900/30 space-y-6">
            <div>
                <h3 className="text-blue-300 text-lg font-semibold mb-2">
                    Getting Started
                </h3>
                <p className="text-gray-400 text-sm mb-2">
                    Create a token above, then include it in every request:
                </p>
                <div className="bg-blue-950 p-3 rounded border border-blue-800 text-sm font-mono text-gray-300">
                    Authorization: Bearer {"<your-token>"}
                </div>
                <p className="text-gray-500 text-xs mt-2">
                    Rate limit: 60 requests per minute per token.
                </p>
            </div>

            <div>
                <h3 className="text-blue-300 text-lg font-semibold mb-3">
                    Endpoints
                </h3>
                {SECTIONS.map((section) => (
                    <div key={section.title} className="mb-4">
                        <h4 className="text-gray-300 text-sm font-semibold mb-1">
                            {section.title}
                        </h4>
                        <EndpointTable endpoints={section.endpoints} />
                    </div>
                ))}
            </div>

            <div>
                <h3 className="text-blue-300 text-lg font-semibold mb-2">
                    Example
                </h3>
                <div className="bg-blue-950 p-3 rounded border border-blue-800 text-sm font-mono text-gray-300 whitespace-pre-wrap overflow-x-auto">
{`curl -X GET "https://your-domain.com/api/v1/files" \\
  -H "Authorization: Bearer <your-token>"`}
                </div>
            </div>
        </div>
    );
}
