import Header from "@/Pages/Drive/Layouts/Header.jsx";
import {router, usePage} from "@inertiajs/react";
import {useState, useEffect} from "react";
import RefreshButton from "@/Pages/Drive/Components/RefreshButton.jsx";
import ToggleRow from "@/Components/ToggleRow.jsx";
import {useLocalStorageBool} from "@/Pages/Drive/Hooks/useLocalStorageBool.jsx";
import ToggleTwoFactorModal from "@/Pages/Admin/Components/ToggleTwoFactorModal.jsx";
import AlertBox from "@/Pages/Drive/Components/AlertBox.jsx";

const TABS = [
    ['config', 'Config'],
    ['tokens', 'API Tokens'],
    ['docs', 'Documentation'],
];

function getInitialTab() {
    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab');
        if (TABS.some(([key]) => key === tab)) return tab;
    }
    return 'config';
}

export default function Settings({
    storage_path,
    php_max_upload_size,
    php_post_max_size,
    php_max_file_uploads,
    setupMode,
    twoFactorStatus,
    show_two_factor_option,
    tokens = [],
    flash = {},
}) {
    const [activeTab, setActiveTab] = useState(getInitialTab);

    // ── Config tab state ──────────────────────────────────────────
    const [formData, setFormData] = useState({
        storage_path:
            storage_path || "/var/www/html/personal-drive-storage-folder",
        php_max_upload_size: php_max_upload_size,
        php_post_max_size: php_post_max_size,
        php_max_file_uploads: php_max_file_uploads,
    });
    const [isTwoFaModalOpen, setIsTwoFaModalOpen] = useState(false);
    const [videoAutoplay, setVideoAutoplay] =
        useLocalStorageBool("videoAutoplay");
    const [audioAutoplay, setAudioAutoplay] =
        useLocalStorageBool("audioAutoplay");
    const [audioSavePos, setAudioSavePos] =
        useLocalStorageBool("audioSavePosition");

    function handleChange(e) {
        setFormData((oldValues) => ({
            ...oldValues,
            [e.target.name]: e.target.value,
        }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        router.post("/admin-config/update", formData);
    }

    function handleToggle2FaStatusButton() {
        setIsTwoFaModalOpen(true);
    }

    function handleVideoAutoplayToggle() {
        localStorage.setItem("videoAutoplay", JSON.stringify(!videoAutoplay));
        setVideoAutoplay(!videoAutoplay);
    }

    function handleAudioAutoplayToggle() {
        localStorage.setItem("audioAutoplay", JSON.stringify(!audioAutoplay));
        setAudioAutoplay(!audioAutoplay);
    }

    function handleAudioSavePosToggle() {
        localStorage.setItem(
            "audioSavePosition",
            JSON.stringify(!audioSavePos),
        );
        setAudioSavePos(!audioSavePos);
    }

    // ── API Tokens tab state ──────────────────────────────────────
    const [showTokenModal, setShowTokenModal] = useState(false);
    const [createdToken, setCreatedToken] = useState(null);
    const [tokenName, setTokenName] = useState("");
    const [processing, setProcessing] = useState(false);
    const [tokenErrors, setTokenErrors] = useState({});

    useEffect(() => {
        if (flash?.plain_text_token) {
            setCreatedToken(flash.plain_text_token);
            setShowTokenModal(true);
        }
    }, [flash]);

    function handleCreate(e) {
        e.preventDefault();
        setProcessing(true);
        setTokenErrors({});

        router.post(
            route("admin.api-tokens.store"),
            {name: tokenName},
            {
                onSuccess: (page) => {
                    const plain = page.props.flash?.plain_text_token;
                    if (plain) {
                        setCreatedToken(plain);
                        setShowTokenModal(true);
                        setTokenName("");
                    }
                    setProcessing(false);
                },
                onError: (err) => {
                    setTokenErrors(err);
                    setProcessing(false);
                },
            }
        );
    }

    function handleDelete(tokenId) {
        if (!confirm("Are you sure you want to delete this token?")) return;
        router.delete(route("admin.api-tokens.destroy", tokenId));
    }

    function copyToken() {
        navigator.clipboard.writeText(createdToken);
    }

    // ── Tab switching ─────────────────────────────────────────────
    function switchTab(tab) {
        setActiveTab(tab);
        const url = new URL(window.location);
        url.searchParams.set("tab", tab);
        window.history.replaceState({}, "", url);
    }

    return (
        <>
            {!setupMode && <Header/>}

            <div className="p-1 sm:p-4 space-y-4 max-w-7xl mx-auto text-gray-200 bg-gray-800">
                <h2 className="text-center text-4xl font-semibold text-gray-300 my-12 mb-6">
                    Admin Settings
                </h2>

                {/* Tab bar */}
                <div className="flex gap-1 border-b border-blue-900/30 mb-6 max-w-3xl mx-auto">
                    {TABS.map(([key, label]) => (
                        <button
                            key={key}
                            onClick={() => switchTab(key)}
                            className={`px-4 py-2 text-sm font-medium rounded-t-lg transition-colors ${
                                activeTab === key
                                    ? "bg-blue-900/30 text-blue-200 border-b-2 border-blue-400"
                                    : "text-gray-400 hover:text-gray-200 hover:bg-slate-800/50"
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                <main className="mx-auto max-w-7xl">
                    <AlertBox/>

                    <div className="max-w-3xl mx-auto bg-blue-900/15 p-2 md:p-12 min-h-[500px] flex flex-col gap-y-8 md:gap-y-20">

                        {/* ═══════ Config Tab ═══════ */}
                        {activeTab === "config" && (
                            <>
                                <form
                                    className="flex flex-col justify-between gap-y-3 md:gap-y-6"
                                    onSubmit={handleSubmit}
                                >
                                    <div className="space-y-4">
                                        <h2 className=" text-blue-200 text-2xl font-bold mt-2 mb-2 ">Storage Path</h2>

                                        <div className="bg-slate-900/50 p-2 md:p-4 rounded-lg border border-blue-900/30">
                                            <p className=" mb-4 ">
                                                Set the local folder where your
                                                files will be stored.
                                            </p>
                                            <div className="flex items-center gap-2 bg-blue-950 p-0 md:p-2 rounded border border-blue-800">
                                                <span className="text-blue-400 hidden md:inline">📁</span>
                                                <input
                                                    className="bg-transparent w-full text-gray-300 outline-none border-0"
                                                    value={formData.storage_path}
                                                    onChange={handleChange}
                                                    name="storage_path"
                                                    id="storage_path"
                                                />
                                            </div>
                                            <ul className="mt-4 space-y-1 text-xs text-gray-400">
                                                <li>• Root directory for all application data</li>
                                                <li>• Changing this <span className="text-orange-400 font-bold">will not move</span> existing files</li>
                                                <li>• <span className="text-red-400">Warning:</span> All active shares will be reset</li>
                                            </ul>

                                            <div className="flex justify-center mt-3 md:mt-6">
                                                <button
                                                    className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 active:bg-blue-800 ">
                                                    {setupMode && "Set Root Folder"}
                                                    {!setupMode && "Update Settings"}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                {show_two_factor_option &&
                                    <div>
                                        <h2 className=" text-blue-200 text-2xl font-bold mt-2 mb-2 ">
                                            Security
                                        </h2>
                                        <ToggleTwoFactorModal
                                            isTwoFaModalOpen={isTwoFaModalOpen}
                                            setIsTwoFaModalOpen={setIsTwoFaModalOpen}
                                            twoFactorStatus={twoFactorStatus}
                                        />
                                        <div className="flex items-center justify-between w-full max-w-sm py-3">
                                            <span className="text-gray-200 text-lg md:font-semibold">Two factor authentication</span>

                                            {twoFactorStatus &&
                                                <button
                                                    onClick={handleToggle2FaStatusButton}
                                                    className="px-2 md:px-3 py-1 bg-gray-700 hover:bg-gray-600 text-green-400 text-sm font-bold rounded border border-gray-500"
                                                >
                                                    ENABLED ❯
                                                </button>
                                            }
                                            {!twoFactorStatus &&
                                                <button
                                                    onClick={handleToggle2FaStatusButton}
                                                    className="px-2 md:px-3 py-1 bg-gray-700 hover:bg-gray-600 text-red-400 text-sm font-bold rounded border border-gray-500 whitespace-nowrap"
                                                >
                                                    DISABLED ❯
                                                </button>
                                            }
                                        </div>
                                    </div>
                                }
                                <div>
                                    <h2 className=" text-blue-200 text-2xl md:font-semibold mt-2 mb-2 ">
                                        Media Settings
                                    </h2>
                                    <div className="flex flex-col space-y-2">
                                        <ToggleRow
                                            label="Autoplay Videos"
                                            enabled={videoAutoplay}
                                            onToggle={handleVideoAutoplayToggle}
                                        />
                                        <ToggleRow
                                            label="Autoplay Audios"
                                            enabled={audioAutoplay}
                                            onToggle={handleAudioAutoplayToggle}
                                        />
                                        <ToggleRow
                                            label="Save Position of Audios"
                                            enabled={audioSavePos}
                                            onToggle={handleAudioSavePosToggle}
                                        />
                                    </div>
                                </div>

                                <div className="rounded-lg max-w-xl">
                                    <h2 className="text-blue-200 text-lg font-bold">Refresh Database</h2>

                                    <div className="border border-blue-900/50 bg-slate-800/30 flex flex-col md:flex-row md:items-center justify-between gap-4  p-2">
                                        <div>
                                            <p className="text-sm text-slate-400 mt-1">
                                                Full system reset: Reindexes files and regenerates thumbnails.
                                            </p>
                                        </div>
                                        <div className="flex flex-col items-end gap-2">
                                            <RefreshButton />
                                            <span className="text-[10px] text-red-400 font-bold uppercase tracking-wider">
        ⚠️ Removes all shares
      </span>
                                        </div>
                                    </div>
                                </div>
                            </>
                        )}

                        {/* ═══════ API Tokens Tab ═══════ */}
                        {activeTab === "tokens" && (
                            <>
                                {/* Create Token */}
                                <div>
                                    <h2 className="text-blue-200 text-2xl font-bold mt-2 mb-2">
                                        Create Token
                                    </h2>
                                    <div className="bg-slate-900/50 p-2 md:p-4 rounded-lg border border-blue-900/30">
                                        <form
                                            className="flex items-center gap-3"
                                            onSubmit={handleCreate}
                                        >
                                            <input
                                                type="text"
                                                value={tokenName}
                                                onChange={(e) =>
                                                    setTokenName(e.target.value)
                                                }
                                                placeholder="Token name (e.g. 'my-app')"
                                                className="flex-1 bg-blue-950 p-2 rounded border border-blue-800 text-gray-300 outline-none"
                                            />
                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 active:bg-blue-800 disabled:opacity-50"
                                            >
                                                Create
                                            </button>
                                        </form>
                                        {tokenErrors.name && (
                                            <p className="text-red-400 text-sm mt-2">
                                                {tokenErrors.name}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {/* Existing Tokens */}
                                <div>
                                    <h2 className="text-blue-200 text-2xl font-bold mt-2 mb-2">
                                        Existing Tokens
                                    </h2>
                                    <div className="bg-slate-900/50 p-2 md:p-4 rounded-lg border border-blue-900/30">
                                        {tokens.length === 0 ? (
                                            <p className="text-gray-400 text-sm">
                                                No API tokens yet.
                                            </p>
                                        ) : (
                                            <div className="overflow-x-auto">
                                                <table className="w-full text-sm">
                                                    <thead>
                                                        <tr className="border-b border-blue-900/30 text-gray-400">
                                                            <th className="text-left py-2 pr-4">
                                                                Name
                                                            </th>
                                                            <th className="text-left py-2 pr-4">
                                                                Created
                                                            </th>
                                                            <th className="text-left py-2 pr-4">
                                                                Last Used
                                                            </th>
                                                            <th className="py-2"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {tokens.map((token) => (
                                                            <tr
                                                                key={token.id}
                                                                className="border-b border-blue-900/20"
                                                            >
                                                                <td className="py-2 pr-4 text-gray-200">
                                                                    {token.name}
                                                                </td>
                                                                <td className="py-2 pr-4 text-gray-400">
                                                                    {new Date(
                                                                        token.created_at
                                                                    ).toLocaleDateString()}
                                                                </td>
                                                                <td className="py-2 pr-4 text-gray-400">
                                                                    {token.last_used_at
                                                                        ? new Date(
                                                                              token.last_used_at
                                                                          ).toLocaleDateString()
                                                                        : "Never"}
                                                                </td>
                                                                <td className="py-2">
                                                                    <button
                                                                        onClick={() =>
                                                                            handleDelete(
                                                                                token.id
                                                                            )
                                                                        }
                                                                        className="px-2 py-1 bg-red-900/50 hover:bg-red-800 text-red-300 text-xs rounded border border-red-800"
                                                                    >
                                                                        Delete
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* API Documentation (inline with tokens) */}
                                <div>
                                    <h2 className="text-blue-200 text-2xl font-bold mt-2 mb-2">
                                        API Documentation
                                    </h2>
                                    <div className="bg-slate-900/50 p-4 md:p-6 rounded-lg border border-blue-900/30 space-y-6">
                                        {/* Getting Started */}
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

                                        {/* Endpoint Reference */}
                                        <div>
                                            <h3 className="text-blue-300 text-lg font-semibold mb-3">
                                                Endpoints
                                            </h3>

                                            {/* Files */}
                                            <h4 className="text-gray-300 text-sm font-semibold mb-1">
                                                Files
                                            </h4>
                                            <div className="overflow-x-auto mb-4">
                                                <table className="w-full text-sm">
                                                    <thead>
                                                        <tr className="border-b border-blue-900/30 text-gray-400">
                                                            <th className="text-left py-1 pr-3">
                                                                Method
                                                            </th>
                                                            <th className="text-left py-1 pr-3">
                                                                URL
                                                            </th>
                                                            <th className="text-left py-1 pr-3">
                                                                Description
                                                            </th>
                                                            <th className="text-left py-1">
                                                                Parameters
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="text-gray-300">
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-green-400 font-mono text-xs bg-green-900/30 px-1 rounded">
                                                                    GET
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/files
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                List files (paginated)
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400">
                                                                path, per_page
                                                            </td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-green-400 font-mono text-xs bg-green-900/30 px-1 rounded">
                                                                    GET
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/files/:id
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Get file info
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400"></td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-blue-400 font-mono text-xs bg-blue-900/30 px-1 rounded">
                                                                    POST
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/files/upload
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Upload files
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400">
                                                                files[], path
                                                            </td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-blue-400 font-mono text-xs bg-blue-900/30 px-1 rounded">
                                                                    POST
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/files/create
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Create file/folder
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400">
                                                                name, type, path
                                                            </td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-green-400 font-mono text-xs bg-green-900/30 px-1 rounded">
                                                                    GET
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/files/:id/download
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Download file
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400"></td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-red-400 font-mono text-xs bg-red-900/30 px-1 rounded">
                                                                    DELETE
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/files/:id
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Delete file
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400"></td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-blue-400 font-mono text-xs bg-blue-900/30 px-1 rounded">
                                                                    POST
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/files/move
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Move files
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400">
                                                                fileList[], destination
                                                            </td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-blue-400 font-mono text-xs bg-blue-900/30 px-1 rounded">
                                                                    POST
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/files/:id/rename
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Rename file
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400">
                                                                name
                                                            </td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-blue-400 font-mono text-xs bg-blue-900/30 px-1 rounded">
                                                                    POST
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/files/:id/save
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Save file content
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400">
                                                                content
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            {/* Search */}
                                            <h4 className="text-gray-300 text-sm font-semibold mb-1">
                                                Search
                                            </h4>
                                            <div className="overflow-x-auto mb-4">
                                                <table className="w-full text-sm">
                                                    <tbody className="text-gray-300">
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3 w-20">
                                                                <span className="text-green-400 font-mono text-xs bg-green-900/30 px-1 rounded">
                                                                    GET
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/search?q=...
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Search files (paginated)
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400">
                                                                q
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            {/* Favorites */}
                                            <h4 className="text-gray-300 text-sm font-semibold mb-1">
                                                Favorites
                                            </h4>
                                            <div className="overflow-x-auto mb-4">
                                                <table className="w-full text-sm">
                                                    <tbody className="text-gray-300">
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3 w-20">
                                                                <span className="text-green-400 font-mono text-xs bg-green-900/30 px-1 rounded">
                                                                    GET
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/favorites
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                List favorites (paginated)
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400"></td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-blue-400 font-mono text-xs bg-blue-900/30 px-1 rounded">
                                                                    POST
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/favorites
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Add favorite
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400">
                                                                local_file_ids[]
                                                            </td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-red-400 font-mono text-xs bg-red-900/30 px-1 rounded">
                                                                    DELETE
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/favorites/:id
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Remove favorite
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400"></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            {/* Shares */}
                                            <h4 className="text-gray-300 text-sm font-semibold mb-1">
                                                Shares
                                            </h4>
                                            <div className="overflow-x-auto mb-4">
                                                <table className="w-full text-sm">
                                                    <tbody className="text-gray-300">
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3 w-20">
                                                                <span className="text-green-400 font-mono text-xs bg-green-900/30 px-1 rounded">
                                                                    GET
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/shares
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                List shares (paginated)
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400"></td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-blue-400 font-mono text-xs bg-blue-900/30 px-1 rounded">
                                                                    POST
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/shares
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Create share
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400">
                                                                fileList[], slug?, password?, expiry?
                                                            </td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-red-400 font-mono text-xs bg-red-900/30 px-1 rounded">
                                                                    DELETE
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/shares/:id
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Delete share
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400"></td>
                                                        </tr>
                                                        <tr className="border-b border-blue-900/20">
                                                            <td className="py-1 pr-3">
                                                                <span className="text-blue-400 font-mono text-xs bg-blue-900/30 px-1 rounded">
                                                                    POST
                                                                </span>
                                                            </td>
                                                            <td className="py-1 pr-3 font-mono text-xs">
                                                                /api/v1/shares/:id/toggle
                                                            </td>
                                                            <td className="py-1 pr-3">
                                                                Toggle share enabled/disabled
                                                            </td>
                                                            <td className="py-1 text-xs text-gray-400"></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        {/* Example */}
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
                                </div>
                            </>
                        )}

                        {/* ═══════ Documentation Tab ═══════ */}
                        {activeTab === "docs" && (
                            <div className="space-y-8">
                                <div className="overflow-x-scroll">
                                    <h2 className=" text-blue-200 text-2xl font-bold mt-2 mb-2 ">
                                        Increase Upload Limits
                                    </h2>
                                    <p className=" mb-6 ">
                                        PHP OR your webserver default upload limits are
                                        too small for most people.
                                    </p>

                                    <p className=" text-blue-200 text-lg font-bold mt-10 mb-5  ">
                                        Current Server PHP Upload Size Limits
                                    </p>
                                    <div className=" mb-0 flex  mx-auto items-baseline gap-x-2 w-full">
                                        <p className="  font-bold ">Max upload size:</p>
                                        <p className="text-lg text-gray-200 text-right mt-1">
                                            {php_max_upload_size}
                                        </p>
                                    </div>
                                    <div className=" flex  mx-auto items-baseline gap-x-2 w-full">
                                        <p className="  font-bold ">
                                            Post upload size:
                                        </p>
                                        <p className="text-lg text-gray-200 text-right mt-1">
                                            {php_post_max_size}
                                        </p>
                                    </div>
                                    <div className=" flex  mx-auto items-baseline gap-x-2 w-full">
                                        <p className="  font-bold ">
                                            Max File Uploads:
                                        </p>
                                        <p className="text-lg text-gray-200 text-right mt-1">
                                            {php_max_file_uploads}
                                        </p>
                                    </div>

                                    <p className="text-lg text-blue-200 mt-10 mb-5 font-bold">
                                        Instructions for various apps:
                                    </p>
                                    <div className="flex flex-col text-gray-300 ">
                                        <div>
                                            <span className="font-bold text-lg text-gray-100">
                                                {" "}
                                                php-fpm:
                                            </span>{" "}
                                            Edit the www.conf file
                                            <pre className="mt-1 mb-5 text-sm text-gray-400">
                                                {`php_value[upload_max_filesize] = 1G
php_value[post_max_size] = 1G
php_value[max_file_uploads] = 1000`}
                                            </pre>
                                        </div>
                                        <div>
                                            <span className="font-bold text-lg text-gray-100">
                                                {" "}
                                                PHP:
                                            </span>{" "}
                                            Edit 3 variables in php.ini file
                                            <pre className="mt-1 mb-5 text-sm text-gray-400">
                                                {`upload_max_filesize = 1G
post_max_size = 1G
max_file_uploads = 10000`}
                                            </pre>
                                        </div>
                                        <div>
                                            <span className="font-bold text-lg text-gray-100">
                                                {" "}
                                                apache:
                                            </span>{" "}
                                            edit the .htaccess file in /public
                                            <pre className="mt-1 mb-5 text-sm text-gray-400">
                                                {`php_value upload_max_filesize 64M
php_value post_max_size 64M
php_value max_file_uploads 10000`}
                                            </pre>
                                        </div>
                                        <div>
                                            <span className="font-bold text-lg text-gray-100">
                                                {" "}
                                                nginx:
                                            </span>{" "}
                                            Increase client_max_body_size param
                                            <pre className="mt-1 mb-5 text-sm text-gray-400">
                                                {`http {
    client_max_body_size 1000M;
}`}
                                            </pre>
                                        </div>
                                        <div>
                                            <span className="font-bold text-lg text-gray-100">
                                                {" "}
                                                Caddy:
                                            </span>{" "}
                                            Increase request_timeout param
                                            <pre className="mt-1 mb-5 text-sm text-gray-400">
                                                {`demo.personaldrive.xyz {
    root * /some/folder
    php_fastcgi unix/{{ php_fpm_socket.stdout }}
    file_server
    request_body {
        max_size 1G
        timeout 1000s
    }
}`}
                                            </pre>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h2 className="text-blue-200 text-2xl font-bold mt-2 mb-2">
                                        About PersonalDrive
                                    </h2>
                                    <div className="bg-slate-900/50 p-4 md:p-6 rounded-lg border border-blue-900/30 space-y-4 text-gray-300 text-sm">
                                        <p>
                                            PersonalDrive is a self-hosted file management application built
                                            with Laravel and React. It provides a web-based interface for
                                            browsing, uploading, sharing, and organizing your files — with
                                            a REST API for programmatic access.
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <h2 className="text-blue-200 text-2xl font-bold mt-2 mb-2">
                                        Getting Started
                                    </h2>
                                    <div className="bg-slate-900/50 p-4 md:p-6 rounded-lg border border-blue-900/30 space-y-4 text-gray-300 text-sm">
                                        <div>
                                            <h3 className="text-blue-300 font-semibold mb-1">1. Set your storage path</h3>
                                            <p>
                                                Go to the <strong>Config</strong> tab and set the storage path to
                                                the local folder where your files live. This is the root directory
                                                for all application data.
                                            </p>
                                        </div>
                                        <div>
                                            <h3 className="text-blue-300 font-semibold mb-1">2. Configure upload limits</h3>
                                            <p>
                                                PHP default upload limits are often too small. See the upload limit
                                                instructions in this <strong>Documentation</strong> tab to increase
                                                them for your web server.
                                            </p>
                                        </div>
                                        <div>
                                            <h3 className="text-blue-300 font-semibold mb-1">3. Create API tokens</h3>
                                            <p>
                                                For programmatic access, switch to the <strong>API Tokens</strong> tab
                                                and create a token. Use it in the <code>Authorization</code> header
                                                of your API requests.
                                            </p>
                                        </div>
                                        <div>
                                            <h3 className="text-blue-300 font-semibold mb-1">4. Enable two-factor authentication</h3>
                                            <p>
                                                For added security, enable 2FA in the <strong>Config</strong> tab
                                                under the Security section.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h2 className="text-blue-200 text-2xl font-bold mt-2 mb-2">
                                        FAQ
                                    </h2>
                                    <div className="bg-slate-900/50 p-4 md:p-6 rounded-lg border border-blue-900/30 space-y-4 text-gray-300 text-sm">
                                        <div>
                                            <h3 className="text-blue-300 font-semibold mb-1">
                                                Will changing the storage path move my files?
                                            </h3>
                                            <p>
                                                No. Changing the storage path does <span className="text-orange-400 font-bold">not</span> move
                                                existing files. It only tells the application where to look for files.
                                                Your existing files remain in their original location.
                                            </p>
                                        </div>
                                        <div>
                                            <h3 className="text-blue-300 font-semibold mb-1">
                                                What does "Refresh Database" do?
                                            </h3>
                                            <p>
                                                It performs a full re-index of your storage directory: reindexes all
                                                files and regenerates thumbnails. Note that this will also remove all
                                                active shares.
                                            </p>
                                        </div>
                                        <div>
                                            <h3 className="text-blue-300 font-semibold mb-1">
                                                How do API tokens work?
                                            </h3>
                                            <p>
                                                API tokens are used to authenticate REST API requests. Create a token
                                                in the API Tokens tab, then include it as a Bearer token in the
                                                <code>Authorization</code> header. Tokens are limited to 60 requests
                                                per minute.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </main>
            </div>

            {/* One-time Token Display Modal */}
            {showTokenModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
                    <div className="bg-gray-900 border border-blue-900/50 rounded-lg p-6 max-w-md w-full mx-4">
                        <h3 className="text-lg font-semibold text-gray-200 mb-2">
                            Token Created
                        </h3>
                        <p className="text-gray-400 text-sm mb-4">
                            Copy this token now. You won't be able to see it
                            again.
                        </p>
                        <div className="bg-blue-950 p-3 rounded border border-blue-800 text-gray-300 text-sm font-mono break-all mb-4">
                            {createdToken}
                        </div>
                        <div className="flex gap-3">
                            <button
                                onClick={copyToken}
                                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                            >
                                Copy
                            </button>
                            <button
                                onClick={() => {
                                    setShowTokenModal(false);
                                    setCreatedToken(null);
                                }}
                                className="px-4 py-2 bg-gray-700 text-gray-300 rounded-md hover:bg-gray-600"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
