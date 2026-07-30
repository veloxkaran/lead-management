// Alpine component powering the "Paste from Spreadsheet" tab on the Raw Data
// bulk-upload page (resources/views/raw-data/bulk-upload.blade.php). Lets a
// user paste a block of cells copied from Excel/Google Sheets straight into
// a grid, see per-row validation live, then submit as JSON — the server
// (RawDataBulkUploadController::storePasted()) re-validates with the same
// rules regardless, this is purely for in-browser visualization before submit.
const RAW_DATA_PASTE_COLUMNS = ['contact_person', 'company_name', 'number_of_employees', 'phone', 'email', 'source', 'notes'];
const RAW_DATA_EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function emptyRawDataRow() {
    return { contact_person: '', company_name: '', number_of_employees: '', phone: '', email: '', source: '', notes: '' };
}

window.rawDataPasteGrid = function (initialRowCount) {
    return {
        rows: Array.from({ length: initialRowCount }, emptyRawDataRow),

        addRows(count) {
            for (let i = 0; i < count; i++) {
                this.rows.push(emptyRawDataRow());
            }
        },

        removeRow(index) {
            this.rows.splice(index, 1);

            if (this.rows.length === 0) {
                this.rows.push(emptyRawDataRow());
            }
        },

        clearAll() {
            this.rows = Array.from({ length: initialRowCount }, emptyRawDataRow);
        },

        isRowBlank(row) {
            return RAW_DATA_PASTE_COLUMNS.every((column) => !row[column] || !row[column].trim());
        },

        duplicatePhones() {
            const counts = new Map();

            this.rows.forEach((row) => {
                const phone = (row.phone || '').trim().toLowerCase();

                if (phone) {
                    counts.set(phone, (counts.get(phone) || 0) + 1);
                }
            });

            return new Set(Array.from(counts.entries()).filter(([, count]) => count > 1).map(([phone]) => phone));
        },

        rowErrors(row) {
            if (this.isRowBlank(row)) {
                return {};
            }

            const errors = {};

            if (!row.contact_person || !row.contact_person.trim()) {
                errors.contact_person = 'Required';
            }

            if (!row.phone || !row.phone.trim()) {
                errors.phone = 'Required';
            } else if (this.duplicatePhones().has(row.phone.trim().toLowerCase())) {
                errors.phone = 'Duplicate phone in this sheet';
            }

            if (row.email && row.email.trim() && !RAW_DATA_EMAIL_RE.test(row.email.trim())) {
                errors.email = 'Invalid email';
            }

            if (row.number_of_employees && row.number_of_employees.trim() && !/^\d+$/.test(row.number_of_employees.trim())) {
                errors.number_of_employees = 'Must be a whole number';
            }

            return errors;
        },

        get filledRows() {
            return this.rows.filter((row) => !this.isRowBlank(row));
        },

        get validRows() {
            return this.filledRows.filter((row) => Object.keys(this.rowErrors(row)).length === 0);
        },

        get invalidCount() {
            return this.filledRows.length - this.validRows.length;
        },

        // Only intercepts multi-cell pastes (tab/newline present) so a plain
        // single-value paste still goes through the browser's normal input
        // handling and plays nicely with x-model.
        handlePaste(event, rowIndex, colIndex) {
            const text = (event.clipboardData || window.clipboardData).getData('text');

            if (!text || (!text.includes('\t') && !text.includes('\n'))) {
                return;
            }

            event.preventDefault();

            const lines = text.replace(/\r/g, '').split('\n');

            while (lines.length && lines[lines.length - 1] === '') {
                lines.pop();
            }

            lines.forEach((line, lineIndex) => {
                const targetRow = rowIndex + lineIndex;

                while (this.rows.length <= targetRow) {
                    this.rows.push(emptyRawDataRow());
                }

                line.split('\t').forEach((value, cellIndex) => {
                    const targetCol = colIndex + cellIndex;

                    if (targetCol < RAW_DATA_PASTE_COLUMNS.length) {
                        this.rows[targetRow][RAW_DATA_PASTE_COLUMNS[targetCol]] = value.trim();
                    }
                });
            });
        },

        onSubmit(event) {
            const submit = () => {
                this.$refs.rowsInput.value = JSON.stringify(this.filledRows);
                event.target.submit();
            };

            if (this.invalidCount > 0 && window.Swal) {
                window.Swal.fire({
                    title: 'Some rows have issues',
                    text: `${this.invalidCount} row(s) have errors and will be skipped on import. Submit the rest anyway?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Submit anyway',
                }).then((result) => {
                    if (result.isConfirmed) {
                        submit();
                    }
                });

                return;
            }

            submit();
        },
    };
};
