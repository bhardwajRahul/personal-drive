import React from 'react';

const Modal = ({isOpen, onClose, title, children, classes}) => {
    return (
        isOpen && (
            <div className={`fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-30 text-gray-300`}
                 onClick={() => onClose(false)}>
                <div className={`bg-gray-900 rounded-lg shadow-xl  mx-4 ${classes}`} onClick={e => e.stopPropagation()}>
                    {title && <div className="flex justify-between items-center border-b p-4">
                        <h2 className="text-xl font-semibold">{title}</h2>
                        <button
                            className="text-gray-300 hover:text-gray-700 focus:outline-none"
                            onClick={() => onClose(false)}
                        >
                            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                      d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    }
                    <div className="p-4">
                        {children}
                    </div>
                </div>
            </div>
        )
    );
};

export default Modal;

