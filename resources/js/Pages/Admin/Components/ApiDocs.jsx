import EndpointTable from "./EndpointTable";

export default function ApiDocs({ sections = [] }) {
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
                {sections.map((section) => (
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
