const UploadQueueDialog = ({ items }) => {
    if (!items.length) return null;

    return (
        <aside
            role="status"
            aria-live="polite"
            className="fixed bottom-4 right-4 z-50 w-80 rounded bg-gray-800 p-4 text-sm text-gray-100 shadow-lg"
        >
            <h2 className="mb-2 font-semibold">Uploads queued</h2>

            <ul className="flex flex-col gap-2">
                {items.map((item) => (
                    <li key={item.id} className="flex justify-between gap-3">
                        <span className="truncate">{item.name}</span>
                        <span className="shrink-0 text-gray-300">
                            {item.status === "uploading"
                                ? "Uploading"
                                : "Queued"}
                        </span>
                    </li>
                ))}
            </ul>
        </aside>
    );
};

export default UploadQueueDialog;
