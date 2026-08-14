import { Star, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import useClickOutside from "@/Pages/Drive/Hooks/useClickOutside.jsx";
import DownloadButton from "@/Pages/Drive/Components/DownloadButton.jsx";
import ShowShareModalButton from "@/Pages/Drive/Components/Shares/ShowShareModalButton.jsx";

const FavoritesMenu = ({
    favorites,
    onOpenFavorite,
    onRemoveFavorite,
    setIsShareModalOpen,
    setFilesToShare,
    setStatusMessage,
    statusMessage,
    setAlertStatus,
}) => {
    const [isOpen, setIsOpen] = useState(false);
    const menuRef = useRef(null);
    const triggerRef = useRef(null);

    useClickOutside(menuRef, () => setIsOpen(false));

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const handleKeyDown = (event) => {
            if (event.key !== "Escape") {
                return;
            }

            setIsOpen(false);
            triggerRef.current?.focus();
        };

        document.addEventListener("keydown", handleKeyDown);

        return () => document.removeEventListener("keydown", handleKeyDown);
    }, [isOpen]);

    const handleRemove = async (event, favoriteId) => {
        event.stopPropagation();
        await onRemoveFavorite(favoriteId);
        triggerRef.current?.focus();
    };

    return (
        <div className="relative" ref={menuRef}>
            <button
                ref={triggerRef}
                type="button"
                aria-expanded={isOpen}
                aria-controls="favorites-list"
                className="flex h-11 w-11 items-center justify-center rounded-md border border-yellow-600 text-yellow-300 hover:bg-yellow-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-800 active:bg-yellow-900"
                onClick={() => setIsOpen((open) => !open)}
            >
                <Star className="h-5 w-5" aria-hidden="true" />
                <span className="sr-only">Favorites</span>
            </button>

            {isOpen && (
                <div
                    id="favorites-list"
                    className="fixed inset-x-2 top-20 z-20 rounded-md border border-gray-700 bg-gray-900 p-2 shadow-lg sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-96"
                >
                    <p className="px-2 py-1 text-sm font-medium text-gray-200">
                        Favorites
                    </p>
                    {favorites.length === 0 ? (
                        <p className="px-2 py-3 text-sm text-gray-400">
                            No favorites yet.
                        </p>
                    ) : (
                        <ul className="max-h-96 overflow-y-auto">
                            {favorites.map((favorite) => {
                                const localFile = favorite.local_file;

                                if (!localFile) {
                                    return null;
                                }

                                return (
                                    <li
                                        className="group flex items-center gap-1 rounded-md px-2 py-1 hover:bg-gray-800 focus-within:bg-gray-800"
                                        key={favorite.id}
                                    >
                                        <button
                                            type="button"
                                            className="min-w-0 flex-1 rounded-md px-1 py-2 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
                                            onClick={() => onOpenFavorite(localFile)}
                                        >
                                            <span className="block truncate text-sm text-gray-100">
                                                {localFile.filename}
                                            </span>
                                            <span className="block truncate text-xs text-gray-400">
                                                {localFile.public_path || "Root"}
                                            </span>
                                        </button>
                                        <div className="flex gap-1 md:opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100">
                                            <DownloadButton
                                                isAdmin={true}
                                                classes="h-11 w-11 justify-center"
                                                selectedFiles={new Set([localFile.id])}
                                                setStatusMessage={setStatusMessage}
                                                statusMessage={statusMessage}
                                                setAlertStatus={setAlertStatus}
                                                aria-label={`Download ${localFile.filename}`}
                                                title={`Download ${localFile.filename}`}
                                            />
                                            <ShowShareModalButton
                                                classes="h-11 w-11 justify-center"
                                                setIsShareModalOpen={setIsShareModalOpen}
                                                setFilesToShare={setFilesToShare}
                                                filesToShare={new Set([localFile.id])}
                                                aria-label={`Share ${localFile.filename}`}
                                                title={`Share ${localFile.filename}`}
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            className="flex h-11 w-11 items-center justify-center rounded-md text-red-300 hover:bg-red-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
                                            onClick={(event) =>
                                                handleRemove(event, favorite.id)
                                            }
                                            aria-label={`Remove ${localFile.filename} from favorites`}
                                            title="Remove favorite"
                                        >
                                            <X className="h-5 w-5" aria-hidden="true" />
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
};

export default FavoritesMenu;
