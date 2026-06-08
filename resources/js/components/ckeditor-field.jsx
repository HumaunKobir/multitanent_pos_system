import { useEffect, useRef } from 'react';

export function CKEditorField({ id, value, onChange, height = 200 }) {
    const textareaRef = useRef(null);
    const editorRef = useRef(null);

    useEffect(() => {
        if (!textareaRef.current || typeof window.CKEDITOR === 'undefined') {
            return;
        }

        if (window.CKEDITOR.instances[id]) {
            window.CKEDITOR.instances[id].destroy(true);
        }

        editorRef.current = window.CKEDITOR.replace(textareaRef.current, {
            height,
            versionCheck: false,
            removePlugins: 'elementspath',
            resize_enabled: false,
        });

        editorRef.current.on('instanceReady', () => {
            editorRef.current.setData(value || '');
        });

        editorRef.current.on('change', () => {
            onChange(editorRef.current.getData());
        });

        editorRef.current.on('blur', () => {
            onChange(editorRef.current.getData());
        });

        return () => {
            if (window.CKEDITOR.instances[id]) {
                window.CKEDITOR.instances[id].destroy(true);
                editorRef.current = null;
            }
        };
    }, [height, id]);

    return <textarea id={id} ref={textareaRef} defaultValue={value} className="hidden" />;
}
