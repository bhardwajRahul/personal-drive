import { router } from "@inertiajs/react";

const BATCH_SIZE = 15;

const useThumbnailGenerator = (files, path) => {
    const generateThumbnails = (ids) => {
        const idsReversed = [...ids].reverse();
        const batch = idsReversed.slice(0, BATCH_SIZE);

        router.post(
            "/gen-thumbs",
            { ids: batch, path },
            {
                only: ["files", "flash"],
                preserveScroll: true,
                onSuccess: () => {
                    const remainingIds = idsReversed.slice(BATCH_SIZE);
                    if (remainingIds.length > 0) {
                        generateThumbnails(remainingIds);
                    }
                },
            },
        );
    };

    // Filter files that need thumbnails
    const thumbnailIds = files
        .filter(
            (file) =>
                !file.has_thumbnail &&
                ["image", "video"].includes(file.file_type),
        )
        .map((file) => file.id);
    if (thumbnailIds.length > 0) {
        generateThumbnails(thumbnailIds);
    }
};

export default useThumbnailGenerator;
