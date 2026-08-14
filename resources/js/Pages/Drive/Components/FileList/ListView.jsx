import FileListRow from "./FileListRow.jsx";
import { Link } from "@inertiajs/react";
import SortIcon from "../../Svgs/SortIcon.jsx";

const ListView = ({
    filesCopy,
    token,
    setStatusMessage,
    setAlertStatus,
    handleFileClick,
    isSearch,
    sortCol,
    sortDetails,
    setFilesCopy,
    path,
    selectedFiles,
    handlerSelectFile,
    selectAllToggle,
    handleSelectAllToggle,
    setIsShareModalOpen,
    setFilesToShare,
    isAdmin,
    slug,
    setSelectedFiles,
    setIsRenameModalOpen,
    setFileToRename,
}) => {
    function handleSortClick(e, key) {
        let sortedFiles = sortCol(filesCopy, key);
        setFilesCopy(sortedFiles);
    }

    return (
        <div>
            <hr className="border-gray-600 text-gray-500" />
            <table className="w-full table-auto text-xs md:text-sm">
                <thead className="border-b border-b-gray-600 text-gray-400">
                    <tr>
                        <th
                            className="w-6 p-1 text-center hover:cursor-pointer hover:bg-gray-900 md:w-10"
                            onClick={() => handleSelectAllToggle(filesCopy)}
                        >
                            <input
                                type="checkbox"
                                checked={selectAllToggle}
                                readOnly
                            />
                        </th>
                        <th
                            onClick={(e) => handleSortClick(e, "filename")}
                            className={`w-full p-1 text-left hover:cursor-pointer hover:bg-gray-900 sm:p-2 ${sortDetails.key === "filename" ? "text-blue-400" : ""}`}
                        >
                            <span>Name</span>
                            <SortIcon
                                classes={`${sortDetails.key === "filename" ? "text-blue-500" : "gray"} `}
                            />
                        </th>
                        <th
                            onClick={(e) => handleSortClick(e, "date")}
                            className={`whitespace-nowrap p-1 text-right hover:cursor-pointer hover:bg-gray-900 sm:p-2 ${sortDetails.key === "date" ? "text-blue-400" : ""}`}
                        >
                            <span>Date</span>
                            <SortIcon
                                classes={`${sortDetails.key === "date" ? "text-blue-500" : "gray"} `}
                            />
                        </th>
                        <th
                            onClick={(e) => handleSortClick(e, "size")}
                            className={`hidden whitespace-nowrap p-1 text-right hover:cursor-pointer hover:bg-gray-900 sm:table-cell sm:p-2 ${sortDetails.key === "size" ? "text-blue-400" : ""}`}
                        >
                            <span>Size</span>
                            <SortIcon
                                classes={`${sortDetails.key === "size" ? "text-blue-500" : "gray"} `}
                            />
                        </th>
                        <th
                            onClick={(e) => handleSortClick(e, "file_type")}
                            className={`whitespace-nowrap p-1 text-right hover:cursor-pointer hover:bg-gray-900 sm:p-2 ${sortDetails.key === "file_type" ? "text-blue-400" : ""}`}
                        >
                            <span>Type</span>
                            <SortIcon
                                classes={`${sortDetails.key === "file_type" ? "text-blue-500" : "gray"} `}
                            />
                        </th>
                    </tr>
                </thead>
                <tbody className="text-sm sm:text-base">
                    {(isSearch ||
                        (path &&
                            !path.match(/shared\/[A-Za-z0-9\-_]+$/) &&
                            path !== "/drive")) && (
                        <tr>
                            <td colSpan="5">
                                <Link
                                    className="block w-full cursor-pointer p-1 px-8 hover:bg-gray-700 md:p-4"
                                    title="Go Up"
                                    href={
                                        path
                                            ? path.substring(
                                                  0,
                                                  path.lastIndexOf("/"),
                                              )
                                            : `/drive`
                                    }
                                >
                                    ..
                                </Link>
                            </td>
                        </tr>
                    )}
                    {filesCopy.map((file) => (
                        <FileListRow
                            key={file.id}
                            file={file}
                            isSearch={isSearch}
                            token={token}
                            setStatusMessage={setStatusMessage}
                            setAlertStatus={setAlertStatus}
                            handleFileClick={handleFileClick}
                            isSelected={selectedFiles.has(file.id)}
                            handlerSelectFile={handlerSelectFile}
                            setIsShareModalOpen={setIsShareModalOpen}
                            setFilesToShare={setFilesToShare}
                            isAdmin={isAdmin}
                            path={path}
                            slug={slug}
                            setSelectedFiles={setSelectedFiles}
                            setIsRenameModalOpen={setIsRenameModalOpen}
                            setFileToRename={setFileToRename}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
};

export default ListView;
