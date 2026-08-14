import FileItem from "../FileItem.jsx";
import FolderItem from "../FolderItem.jsx";
import React from "react";

const FileListRow = React.memo(function FileListRow({
    file,
    isSearch,
    token,
    setStatusMessage,
    setAlertStatus,
    handleFileClick,
    isSelected,
    handlerSelectFile,
    setIsShareModalOpen,
    setFilesToShare,
    isAdmin,
    path,
    slug,
    setSelectedFiles,
    setIsRenameModalOpen,
    setFileToRename,
}) {
    return (
        <tr className="group cursor-pointer hover:bg-gray-700">
            <td
                className="w-6 p-1 text-center hover:bg-gray-900 md:w-10"
                onClick={() => handlerSelectFile(file)}
            >
                <input
                    type="checkbox"
                    checked={!!isSelected}
                    onChange={() => {}}
                />
            </td>
            <td className="max-w-0 overflow-hidden p-0">
                {file.is_dir ? (
                    <FolderItem
                        file={file}
                        isSearch={isSearch}
                        token={token}
                        setStatusMessage={setStatusMessage}
                        setAlertStatus={setAlertStatus}
                        setIsShareModalOpen={setIsShareModalOpen}
                        setFilesToShare={setFilesToShare}
                        isAdmin={isAdmin}
                        path={path}
                        slug={slug}
                        setSelectedFiles={setSelectedFiles}
                        setIsRenameModalOpen={setIsRenameModalOpen}
                        setFileToRename={setFileToRename}
                    />
                ) : (
                    <FileItem
                        file={file}
                        isSearch={isSearch}
                        token={token}
                        setStatusMessage={setStatusMessage}
                        setAlertStatus={setAlertStatus}
                        handleFileClick={handleFileClick}
                        setIsShareModalOpen={setIsShareModalOpen}
                        setFilesToShare={setFilesToShare}
                        isAdmin={isAdmin}
                        path={path}
                        slug={slug}
                        setSelectedFiles={setSelectedFiles}
                        setIsRenameModalOpen={setIsRenameModalOpen}
                        setFileToRename={setFileToRename}
                    />
                )}
            </td>
            <td className="whitespace-nowrap p-1 text-right text-xs text-gray-400 sm:p-2 md:text-sm">
                <span className="sm:hidden">
                    {new Date(file.date * 1000).toLocaleDateString(undefined, {
                        month: "2-digit",
                        day: "2-digit",
                    })}
                </span>
                <span className="hidden sm:inline">
                    {new Date(file.date * 1000).toISOString().slice(0, 10)}
                </span>
            </td>
            <td className="hidden whitespace-nowrap p-1 text-right text-xs text-gray-400 sm:table-cell sm:p-2 md:text-sm">
                {file.sizeText}
            </td>
            <td className="whitespace-nowrap p-1 text-right text-xs text-gray-400 sm:p-2 md:text-sm">
                {file.file_type}
            </td>
        </tr>
    );
});

export default FileListRow;
