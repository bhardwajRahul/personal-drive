import useSelectionUtil from "@/Pages/Drive/Hooks/useSelectionutil.jsx";
import FileBrowserSection from "@/Pages/Drive/Components/FileBrowserSection.jsx";

export default function ShareFilesGuestHome({ files, path, token, slug }) {
    const {
        selectAllToggle,
        handleSelectAllToggle,
        selectedFiles,
        handlerSelectFileMemo,
    } = useSelectionUtil();

    return (
        <div className="max-w-7xl mx-autobg-gray-800 text-gray-200 px-2 md:px-0">
            <FileBrowserSection
                files={files}
                path={path}
                isSearch={false}
                token={token}
                selectAllToggle={selectAllToggle}
                handleSelectAllToggle={handleSelectAllToggle}
                selectedFiles={selectedFiles}
                handlerSelectFile={handlerSelectFileMemo}
                isAdmin={false}
                slug={slug}
            />
        </div>
    );
}
