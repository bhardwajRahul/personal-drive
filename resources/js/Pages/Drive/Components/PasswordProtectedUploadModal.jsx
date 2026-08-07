import { useMemo, useState } from "react";
import { useDropzone } from "react-dropzone";
import { router } from "@inertiajs/react";
import { BlobReader, BlobWriter, ZipWriter } from "@zip.js/zip.js";
import { FileIcon, FolderIcon, XIcon } from "lucide-react";
import Modal from "./Modal.jsx";
import InputError from "@/Components/InputError.jsx";

const defaultZipName = () => {
    const d = new Date();
    const p = (n) => String(n).padStart(2, "0");
    return (
        "protected_" +
        `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}` +
        `_${p(d.getHours())}-${p(d.getMinutes())}`
    );
};

// Full path used inside the zip, preserving folder structure.
const relPathOf = (file) =>
    (file.path || file.webkitRelativePath || file.name).replace(/^\.?\/+/, "");

const PasswordProtectedUploadModal = ({
    isModalOpen,
    setIsModalOpen,
    path,
    setStatusMessage,
}) => {
    const [selectedFiles, setSelectedFiles] = useState([]);
    const [zipName, setZipName] = useState(defaultZipName);
    const [password, setPassword] = useState("");
    const [confirmPassword, setConfirmPassword] = useState("");
    const [errors, setErrors] = useState({});
    const [isUploading, setIsUploading] = useState(false);

    const addFiles = (incoming) => {
        if (!incoming.length) return;
        setSelectedFiles((prev) => {
            const seen = new Set(prev.map(relPathOf));
            const merged = [...prev];
            incoming.forEach((f) => {
                const key = relPathOf(f);
                if (!seen.has(key)) {
                    seen.add(key);
                    merged.push(f);
                }
            });
            return merged;
        });
    };

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop: addFiles,
        noClick: true,
        noKeyboard: true,
    });

    // Group to top-level entries only (no recursion in the display).
    const topLevelEntries = useMemo(() => {
        const map = new Map();
        selectedFiles.forEach((f) => {
            const rel = relPathOf(f);
            const top = rel.split("/")[0];
            if (!map.has(top)) {
                map.set(top, { name: top, isFolder: rel.includes("/") });
            }
        });
        return [...map.values()];
    }, [selectedFiles]);

    const handleSelectInput = (e) => {
        addFiles(Array.from(e.target.files || []));
        e.target.value = "";
    };

    const removeEntry = (name) => {
        setSelectedFiles((prev) =>
            prev.filter((f) => relPathOf(f).split("/")[0] !== name),
        );
    };

    const resetForm = () => {
        setSelectedFiles([]);
        setZipName(defaultZipName());
        setPassword("");
        setConfirmPassword("");
        setErrors({});
    };

    const handleClose = (status) => {
        setIsModalOpen(status);
        if (!status) resetForm();
    };

    const validate = () => {
        const next = {};
        if (!selectedFiles.length)
            next.files = "Please select at least one file.";
        if (!zipName.trim()) next.zipName = "Please enter a file name.";
        if (!password) next.password = "Please enter a password.";
        if (password !== confirmPassword)
            next.confirmPassword = "Passwords do not match.";
        setErrors(next);
        return Object.keys(next).length === 0;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!validate()) return;

        setIsUploading(true);
        setStatusMessage("Encrypting files...");
        let zipBlob;
        try {
            const zipWriter = new ZipWriter(new BlobWriter("application/zip"), {
                password,
                encryptionStrength: 3, // AES-256
            });
            for (const file of selectedFiles) {
                await zipWriter.add(relPathOf(file), new BlobReader(file));
            }
            zipBlob = await zipWriter.close();
        } catch {
            setStatusMessage("");
            setIsUploading(false);
            setErrors({ files: "Failed to create encrypted zip." });
            return;
        }

        setStatusMessage("Uploading...");
        const formData = new FormData();
        formData.append("files[]", zipBlob, `${zipName.trim()}.zip`);
        formData.append("path", path);
        router.post("/upload", formData, {
            only: ["files", "flash"],
            onError: (error) => {
                if (error.response?.status === 413) {
                    setStatusMessage(
                        "File too large for server to handle. Please upload a smaller file.",
                    );
                }
            },
            onFinish: () => {
                setStatusMessage("");
                setIsUploading(false);
                handleClose(false);
            },
        });
    };

    return (
        <Modal
            isOpen={isModalOpen}
            onClose={handleClose}
            title="Upload Password Protected"
            classes="max-w-md w-full"
        >
            <form onSubmit={handleSubmit} className="space-y-4 text-gray-300">
                <p className="text-sm text-gray-400/90">
                    Files are zipped and encrypted with AES-256 in your browser
                    before uploading. Keep the password safe — it cannot be
                    recovered.
                </p>

                <div
                    {...getRootProps()}
                    className={`rounded-md border-2 border-dashed p-4 text-center text-sm ${
                        isDragActive
                            ? "border-green-400 bg-gray-800"
                            : "border-gray-600 bg-gray-800/40"
                    }`}
                >
                    <input {...getInputProps()} />
                    <p>Drag files or folders here, or</p>
                    <div className="mt-2 flex justify-center gap-2">
                        <button
                            type="button"
                            onClick={() =>
                                document.getElementById("ppFileInput").click()
                            }
                            className="bg-blue-700 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm"
                        >
                            Select Files
                        </button>
                        <button
                            type="button"
                            onClick={() =>
                                document.getElementById("ppFolderInput").click()
                            }
                            className="bg-blue-700 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm"
                        >
                            Select Folder
                        </button>
                    </div>
                    <input
                        id="ppFileInput"
                        type="file"
                        multiple
                        className="hidden"
                        onChange={handleSelectInput}
                    />
                    <input
                        id="ppFolderInput"
                        type="file"
                        webkitdirectory="true"
                        directory="true"
                        className="hidden"
                        onChange={handleSelectInput}
                    />
                </div>
                <InputError message={errors.files} />

                {topLevelEntries.length > 0 && (
                    <div className="max-h-40 overflow-y-auto rounded-md border border-gray-700 bg-gray-800/40 divide-y divide-gray-700">
                        {topLevelEntries.map((entry) => (
                            <div
                                key={entry.name}
                                className="flex items-center gap-2 px-3 py-1.5 text-sm"
                            >
                                {entry.isFolder ? (
                                    <FolderIcon className="w-4 h-4 shrink-0 text-yellow-500" />
                                ) : (
                                    <FileIcon className="w-4 h-4 shrink-0 text-gray-400" />
                                )}
                                <span className="truncate flex-1">
                                    {entry.name}
                                </span>
                                <button
                                    type="button"
                                    onClick={() => removeEntry(entry.name)}
                                    className="text-gray-400 hover:text-red-400"
                                    aria-label={`Remove ${entry.name}`}
                                >
                                    <XIcon className="w-4 h-4" />
                                </button>
                            </div>
                        ))}
                    </div>
                )}

                <div>
                    <label
                        htmlFor="zipName"
                        className="block text-sm font-medium"
                    >
                        File name (.zip added automatically)
                    </label>
                    <input
                        type="text"
                        id="zipName"
                        value={zipName}
                        onChange={(e) => setZipName(e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 bg-gray-800"
                    />
                    <InputError message={errors.zipName} />
                </div>

                <div>
                    <label
                        htmlFor="ppPassword"
                        className="block text-sm font-medium"
                    >
                        Password
                    </label>
                    <input
                        type="password"
                        id="ppPassword"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 bg-gray-800"
                    />
                    <InputError message={errors.password} />
                </div>

                <div>
                    <label
                        htmlFor="ppConfirmPassword"
                        className="block text-sm font-medium"
                    >
                        Confirm Password
                    </label>
                    <input
                        type="password"
                        id="ppConfirmPassword"
                        value={confirmPassword}
                        onChange={(e) => setConfirmPassword(e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 bg-gray-800"
                    />
                    <InputError message={errors.confirmPassword} />
                </div>

                <button
                    type="submit"
                    disabled={isUploading}
                    className={`w-full text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ${
                        isUploading
                            ? "bg-gray-500"
                            : "bg-blue-500 hover:bg-blue-600"
                    }`}
                >
                    {isUploading ? "Working..." : "Upload"}
                </button>
            </form>
        </Modal>
    );
};

export default PasswordProtectedUploadModal;
