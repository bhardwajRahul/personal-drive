function LimitRow({ label, value }) {
    return (
        <div className="flex mx-auto items-baseline gap-x-2 w-full">
            <p className="font-bold">{label}:</p>
            <p className="text-lg text-gray-200 text-right mt-1">{value}</p>
        </div>
    );
}

export default function UploadLimitsDocs({
    php_max_upload_size,
    php_post_max_size,
    php_max_file_uploads,
    server_configs = [],
}) {
    return (
        <div className="overflow-x-scroll">
            <h2 className="text-blue-200 text-2xl font-bold mt-2 mb-2">
                Increase Upload Limits
            </h2>
            <p className="mb-6">
                PHP OR your webserver default upload limits are too small for
                most people.
            </p>

            <p className="text-blue-200 text-lg font-bold mt-10 mb-5">
                Current Server PHP Upload Size Limits
            </p>
            <LimitRow label="Max upload size" value={php_max_upload_size} />
            <LimitRow label="Post upload size" value={php_post_max_size} />
            <LimitRow label="Max File Uploads" value={php_max_file_uploads} />

            <p className="text-lg text-blue-200 mt-10 mb-5 font-bold">
                Instructions for various apps:
            </p>
            <div className="flex flex-col text-gray-300">
                {server_configs.map((config) => (
                    <div key={config.name}>
                        <span className="font-bold text-lg text-gray-100">
                            {config.name}:
                        </span>{" "}
                        {config.instruction}
                        <pre className="mt-1 mb-5 text-sm text-gray-400">
                            {config.code}
                        </pre>
                    </div>
                ))}
            </div>
        </div>
    );
}
