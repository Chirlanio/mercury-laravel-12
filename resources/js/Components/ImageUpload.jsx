import { useState, useRef, useEffect } from 'react';
import { TrashIcon, PhotoIcon, ArrowUpTrayIcon } from '@heroicons/react/24/outline';

export default function ImageUpload({
    label = "Imagem",
    value = null, // Can be a File object, a URL string, or null
    onChange,
    error = null,
    accept = "image/jpeg,image/png,image/gif,image/webp",
    maxSize = 5, // MB
    className = "",
    disabled = false
}) {
    const [preview, setPreview] = useState(null);
    const [dragActive, setDragActive] = useState(false);
    const inputRef = useRef(null);

    useEffect(() => {
        let objectUrl;

        if (value instanceof File) {
            objectUrl = URL.createObjectURL(value);
            setPreview(objectUrl);
        } else if (typeof value === 'string') {
            setPreview(value);
        } else {
            setPreview(null);
        }

        // Cleanup function to revoke the object URL
        return () => {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }
        };
    }, [value]);

    const handleFileSelect = (file) => {
        if (!file || disabled) return;

        const allowedTypes = accept.split(',').map(type => type.trim());
        if (!allowedTypes.includes(file.type)) {
            alert(`Tipo de arquivo não permitido. Use: ${allowedTypes.join(', ')}`);
            return;
        }

        const maxSizeBytes = maxSize * 1024 * 1024;
        if (file.size > maxSizeBytes) {
            alert(`Arquivo muito grande. Tamanho máximo: ${maxSize}MB`);
            return;
        }

        if (onChange) {
            onChange(file);
        }
    };

    const handleInputChange = (e) => {
        if (e.target.files && e.target.files[0]) {
            handleFileSelect(e.target.files[0]);
        }
    };

    const handleDrag = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (disabled) return;
        if (e.type === "dragenter" || e.type === "dragover") {
            setDragActive(true);
        } else if (e.type === "dragleave") {
            setDragActive(false);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);
        if (disabled) return;
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            handleFileSelect(e.dataTransfer.files[0]);
        }
    };

    const openFileDialog = () => {
        if (!disabled) {
            inputRef.current.click();
        }
    };

    const handleRemove = (e) => {
        e.stopPropagation();
        if (disabled) return;
        if (onChange) {
            onChange(null);
        }
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    return (
        <div className={`space-y-2 ${className}`}>
            <label className="block text-sm font-medium text-gray-700">{label}</label>
            <input
                ref={inputRef}
                type="file"
                accept={accept}
                onChange={handleInputChange}
                className="hidden"
                disabled={disabled}
            />
            <div
                className={`relative border-2 border-dashed rounded-lg p-6 text-center transition-colors ${dragActive ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300'} ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:border-indigo-400'} ${error ? 'border-red-300' : ''}`}
                onDragEnter={handleDrag}
                onDragLeave={handleDrag}
                onDragOver={handleDrag}
                onDrop={handleDrop}
                onClick={openFileDialog}
            >
                {preview ? (
                    <div className="relative group">
                        <img src={preview} alt="Preview" className="mx-auto h-32 w-32 object-cover rounded-lg" />
                        {!disabled && (
                            <div className="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 rounded-lg flex items-center justify-center transition-all">
                                <button
                                    type="button"
                                    onClick={handleRemove}
                                    className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                >
                                    <TrashIcon className="h-4 w-4" />
                                </button>
                                <ArrowUpTrayIcon className="h-8 w-8 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                            </div>
                        )}
                    </div>
                ) : (
                    <div>
                        <PhotoIcon className="mx-auto h-12 w-12 text-gray-400" />
                        <div className="mt-4">
                            <p className="text-sm text-gray-600">
                                {disabled ? 'Upload desabilitado' : <><span className="font-medium text-indigo-600">Clique para fazer upload</span>{' ou arraste e solte'}</>}
                            </p>
                            <p className="text-xs text-gray-500 mt-1">PNG, JPG, GIF, WebP até {maxSize}MB</p>
                        </div>
                    </div>
                )}
            </div>
            {error && <p className="text-sm text-red-600 mt-1">{error}</p>}
        </div>
    );
}