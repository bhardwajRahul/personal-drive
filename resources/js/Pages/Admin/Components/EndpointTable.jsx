const METHOD_COLORS = {
    GET: "text-green-400 bg-green-900/30",
    POST: "text-blue-400 bg-blue-900/30",
    DELETE: "text-red-400 bg-red-900/30",
};

export default function EndpointTable({ endpoints }) {
    return (
        <div className="overflow-x-auto mb-4">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-blue-900/30 text-gray-400">
                        <th className="text-left py-1 pr-3">Method</th>
                        <th className="text-left py-1 pr-3">URL</th>
                        <th className="text-left py-1 pr-3">
                            Description
                        </th>
                        <th className="text-left py-1">Parameters</th>
                    </tr>
                </thead>
                <tbody className="text-gray-300">
                    {endpoints.map((ep, i) => (
                        <tr key={i} className="border-b border-blue-900/20">
                            <td className="py-1 pr-3">
                                <span
                                    className={`font-mono text-xs px-1 rounded ${METHOD_COLORS[ep.method]}`}
                                >
                                    {ep.method}
                                </span>
                            </td>
                            <td className="py-1 pr-3 font-mono text-xs">
                                {ep.url}
                            </td>
                            <td className="py-1 pr-3">{ep.description}</td>
                            <td className="py-1 text-xs text-gray-400">
                                {ep.params}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
