(function () {
    'use strict';

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function bindDeviceStatusToggles() {
        document.querySelectorAll('.device-status-toggle').forEach((wrap) => {
            const input = wrap.querySelector('.device-status-switch');
            if (!input || input.dataset.bound === '1') {
                return;
            }
            input.dataset.bound = '1';

            input.addEventListener('change', async function () {
                if (wrap.dataset.blocked === '1') {
                    return;
                }

                const url = wrap.dataset.url;
                const checked = this.checked;
                const label = wrap.querySelector('.status-toggle-label');
                const previousChecked = !checked;
                const previousLabel = checked ? 'Inactive' : 'Active';

                this.disabled = true;

                try {
                    const res = await fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': getCsrfToken(),
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ status: checked ? 'active' : 'inactive' }),
                    });

                    const data = await res.json();

                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Could not update status.');
                    }

                    if (label) {
                        label.textContent = data.label || (checked ? 'Active' : 'Inactive');
                    }

                    if (typeof window.onDeviceStatusToggled === 'function') {
                        window.onDeviceStatusToggled(wrap, data);
                    }
                } catch (err) {
                    this.checked = previousChecked;
                    if (label) {
                        label.textContent = previousLabel;
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Update failed', text: err.message });
                    } else {
                        alert(err.message);
                    }
                } finally {
                    this.disabled = false;
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', bindDeviceStatusToggles);
    window.bindDeviceStatusToggles = bindDeviceStatusToggles;
})();
