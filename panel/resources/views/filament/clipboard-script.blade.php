{{-- Global click-to-copy helper for CopyToClipboardAction (CLAUDE.md
     Section 14). Falls back to document.execCommand when the Clipboard
     API is unavailable, which happens on any insecure (plain HTTP)
     origin - the panel's default deployment per Section 16.

     The function name is pulled from CopyToClipboardAction::JS_HANDLER
     rather than hardcoded here, so this view and that class can't
     independently drift apart on the name they agree to call. --}}
<script>
    window.{{ \App\Filament\Support\CopyToClipboardAction::JS_HANDLER }} = function (text) {
        var notifySuccess = function () {
            new FilamentNotification().title('Password copied').success().seconds(3).send();
        };
        var notifyFailure = function () {
            new FilamentNotification()
                .title('Could not copy automatically')
                .body('Select and copy the password manually.')
                .danger()
                .send();
        };

        if (window.isSecureContext && navigator.clipboard) {
            navigator.clipboard.writeText(text).then(notifySuccess, notifyFailure);

            return;
        }

        try {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            var copied = document.execCommand('copy');
            document.body.removeChild(textarea);

            if (copied) {
                notifySuccess();
            } else {
                notifyFailure();
            }
        } catch (error) {
            notifyFailure();
        }
    };
</script>
