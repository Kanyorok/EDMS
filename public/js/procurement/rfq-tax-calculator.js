(function () {
    const TAX = {
        VAT: 'VAT',
        EXEMPT: 'Exempt',
        WITHHOLDING: 'WithholdingTax',
        DEFAULT_VAT_RATE: 16,
        DEFAULT_WHT_RATE: 6,
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const parseNumber = (value) => {
        if (value === null || value === undefined || value === '') {
            return 0;
        }

        const parsed = Number.parseFloat(String(value).replace(/,/g, ''));

        return Number.isFinite(parsed) ? parsed : 0;
    };

    const round = (value, precision = 4) => {
        const factor = 10 ** precision;

        return Math.round((value + Number.EPSILON) * factor) / factor;
    };

    const toOptionBoolean = (value) => value === true || value === 1 || value === '1' || value === 'true';

    const formatDisplay = (value, precision = 2) => new Intl.NumberFormat(undefined, {
        minimumFractionDigits: precision,
        maximumFractionDigits: precision,
    }).format(Number.isFinite(value) ? value : 0);

    const computeLineTax = (unitPrice, quantity, taxType, isTaxInclusive, taxRate, withholdingTaxRate, discountPercentage) => {
        let normalizedTaxType = (taxType || TAX.VAT).trim();
        let normalizedTaxRate = Math.max(0, parseNumber(taxRate));
        let normalizedInclusive = Boolean(isTaxInclusive);
        let normalizedWhtRate = Math.max(0, parseNumber(withholdingTaxRate));
        let normalizedDiscount = Math.min(100, Math.max(0, parseNumber(discountPercentage)));

        const normalizedUnitPrice = Math.max(0, parseNumber(unitPrice));
        const normalizedQuantity = Math.max(0, parseNumber(quantity));

        if (normalizedTaxType === TAX.EXEMPT) {
            normalizedTaxRate = 0;
            normalizedInclusive = false;
            normalizedWhtRate = 0;
        }

        const lineTotal = round(normalizedUnitPrice * normalizedQuantity);
        const discountAmount = round(lineTotal * (normalizedDiscount / 100));
        const discountedLineTotal = round(Math.max(0, lineTotal - discountAmount));

        let netAmount;
        let taxAmount;
        let grossAmount;

        if (normalizedTaxRate === 0 || normalizedTaxType === TAX.EXEMPT) {
            netAmount = round(discountedLineTotal);
            taxAmount = 0;
            grossAmount = netAmount;
        } else if (normalizedInclusive) {
            grossAmount = round(discountedLineTotal);
            netAmount = round(grossAmount / (1 + normalizedTaxRate / 100));
            taxAmount = round(grossAmount - netAmount);
        } else {
            netAmount = round(discountedLineTotal);
            taxAmount = round(netAmount * (normalizedTaxRate / 100));
            grossAmount = round(netAmount + taxAmount);
        }

        let withholdingTaxAmount = 0;
        if (normalizedTaxType === TAX.WITHHOLDING && normalizedWhtRate > 0) {
            withholdingTaxAmount = round(netAmount * (normalizedWhtRate / 100));
        }

        return {
            discountAmount,
            netAmount,
            taxAmount,
            grossAmount,
            withholdingTaxAmount,
            discountPercentage: normalizedDiscount,
            taxRate: normalizedTaxRate,
            isTaxInclusive: normalizedInclusive,
            withholdingTaxRate: normalizedWhtRate,
        };
    };

    const getRowFields = (row) => ({
        quantity: row.querySelector('.js-quantity'),
        quotedPrice: row.querySelector('.js-quoted-price'),
        discountPercentage: row.querySelector('.js-discount-percentage'),
        taxRuleId: row.querySelector('.js-tax-rule-id'),
        taxType: row.querySelector('.js-tax-type'),
        taxTypeLabel: row.querySelector('.js-tax-type-label'),
        taxRate: row.querySelector('.js-tax-rate'),
        taxRateLabel: row.querySelector('.js-tax-rate-label'),
        taxInclusive: row.querySelector('.js-tax-inclusive'),
        withholdingTaxRate: row.querySelector('.js-withholding-tax-rate'),
        totalPayable: row.querySelector('.js-totalpayable'),
        discountDisplay: row.querySelector('.js-discount-amount-display'),
        netDisplay: row.querySelector('.js-net-amount-display'),
        taxDisplay: row.querySelector('.js-tax-amount-display'),
        grossDisplay: row.querySelector('.js-gross-amount-display'),
        whtDisplay: row.querySelector('.js-withholding-tax-amount-display'),
    });

    const syncTaxControls = (row) => {
        const fields = getRowFields(row);
        if (!fields.taxType || !fields.taxRate) {
            return;
        }

        if (fields.taxRuleId) {
            const selectedOption = fields.taxRuleId.options[fields.taxRuleId.selectedIndex];
            if (selectedOption) {
                const selectedType = selectedOption.dataset.taxType || TAX.VAT;
                const selectedRate = parseNumber(selectedOption.dataset.taxRate || TAX.DEFAULT_VAT_RATE).toFixed(2);

                fields.taxType.value = selectedType;
                fields.taxRate.value = selectedRate;

                if (fields.taxTypeLabel) {
                    fields.taxTypeLabel.value = selectedOption.textContent.replace(/\s*\([^)]*\)\s*$/, '').trim();
                }

                if (fields.taxRateLabel) {
                    fields.taxRateLabel.value = selectedRate;
                }
            }
        }

        const currentType = fields.taxType.value || TAX.VAT;

        if (currentType === TAX.EXEMPT) {
            fields.taxRate.value = '0.00';
            fields.taxInclusive.value = '0';
            fields.withholdingTaxRate.value = '';
            fields.withholdingTaxRate.setAttribute('readonly', 'readonly');
        } else {
            if (parseNumber(fields.taxRate.value) === 0) {
                fields.taxRate.value = TAX.DEFAULT_VAT_RATE.toFixed(2);
            }

            fields.withholdingTaxRate.removeAttribute('readonly');
        }
    };

    const recalculateRow = (row) => {
        const fields = getRowFields(row);
        if (!fields.quantity || !fields.quotedPrice || !fields.taxType || !fields.totalPayable) {
            return;
        }

        syncTaxControls(row);

        const result = computeLineTax(
            fields.quotedPrice.value,
            fields.quantity.value,
            fields.taxType.value,
            fields.taxInclusive.value === '1',
            fields.taxRate.value,
            fields.withholdingTaxRate.value,
            fields.discountPercentage?.value ?? 0
        );

        row.dataset.discountAmount = String(result.discountAmount);
        row.dataset.netAmount = String(result.netAmount);
        row.dataset.taxAmount = String(result.taxAmount);
        row.dataset.grossAmount = String(result.grossAmount);
        row.dataset.withholdingTaxAmount = String(result.withholdingTaxAmount);

        fields.totalPayable.value = result.grossAmount.toFixed(4);
        if (fields.discountPercentage) {
            fields.discountPercentage.value = result.discountPercentage > 0 ? result.discountPercentage.toFixed(2) : '';
        }
        fields.discountDisplay.textContent = formatDisplay(result.discountAmount);
        fields.netDisplay.textContent = formatDisplay(result.netAmount);
        fields.taxDisplay.textContent = formatDisplay(result.taxAmount);
        fields.grossDisplay.textContent = formatDisplay(result.grossAmount);
        fields.whtDisplay.textContent = formatDisplay(result.withholdingTaxAmount);
    };

    const recalculateTotals = (form) => {
        let totalDiscountAmount = 0;
        let totalNetAmount = 0;
        let totalTaxAmount = 0;
        let totalGrossAmount = 0;
        let totalWithholdingTaxAmount = 0;

        form.querySelectorAll('[data-rfq-item-row]').forEach((row) => {
            recalculateRow(row);

            totalDiscountAmount += parseNumber(row.dataset.discountAmount);
            totalNetAmount += parseNumber(row.dataset.netAmount);
            totalTaxAmount += parseNumber(row.dataset.taxAmount);
            totalGrossAmount += parseNumber(row.dataset.grossAmount);
            totalWithholdingTaxAmount += parseNumber(row.dataset.withholdingTaxAmount);
        });

        const totalDiscountField = form.querySelector('.js-summary-total-discount');
        const totalNetField = form.querySelector('.js-summary-total-net');
        const totalTaxField = form.querySelector('.js-summary-total-tax');
        const totalGrossField = form.querySelector('.js-summary-total-gross');
        const totalGrossDisplayField = form.querySelector('.js-summary-total-gross-display');
        const totalWhtField = form.querySelector('.js-summary-total-wht');

        if (totalDiscountField) {
            totalDiscountField.value = formatDisplay(totalDiscountAmount);
        }

        if (totalNetField) {
            totalNetField.value = formatDisplay(totalNetAmount);
        }

        if (totalTaxField) {
            totalTaxField.value = formatDisplay(totalTaxAmount);
        }

        if (totalGrossField) {
            totalGrossField.value = totalGrossAmount.toFixed(4);
        }

        if (totalGrossDisplayField) {
            totalGrossDisplayField.value = formatDisplay(totalGrossAmount);
        }

        if (totalWhtField) {
            totalWhtField.value = formatDisplay(totalWithholdingTaxAmount);
        }
    };

    const setFormLocked = (form, locked) => {
        form.querySelectorAll('input, select, button[type="submit"]').forEach((element) => {
            if (element.hasAttribute('data-lock-exempt')) {
                return;
            }

            if (locked) {
                element.setAttribute('disabled', 'disabled');
            } else {
                element.removeAttribute('disabled');
            }
        });
    };

    const buildRowMarkup = (index, item, taxTemplate) => `
        <tr data-rfq-item-row>
            <td class="text-center align-middle">${index + 1}</td>
            <td>
                <input type="hidden" name="RequisitionItems[${index}][rfq_line_id]" value="${escapeHtml(item.rfq_line_id ?? item.id ?? '')}">
                <input type="text" name="RequisitionItems[${index}][name]" class="form-control" value="${escapeHtml(item.ItemName ?? item.name ?? '')}" readonly>
            </td>
            <td>
                <input type="hidden" name="RequisitionItems[${index}][uom_id]" value="${escapeHtml(item.UOM ?? item.uom_id ?? item.uom ?? '')}">
                <input type="text" class="form-control" value="${escapeHtml(item.UOMName ?? item.uom_name ?? item.uom ?? 'N/A')}" readonly>
            </td>
            <td>
                <input type="number" name="RequisitionItems[${index}][quantity]" class="form-control js-quantity" step="0.0001" value="${escapeHtml(item.Quantity ?? item.quantity ?? 0)}" readonly required>
            </td>
            <td>
                <input type="number" name="RequisitionItems[${index}][quotedprice]" class="form-control js-quoted-price" step="0.0001" min="0" value="${escapeHtml(item.quotedprice ?? item.quotedPrice ?? '')}" required>
            </td>
            <td>${taxTemplate.replace(/__INDEX__/g, String(index))}</td>
            <td>
                <input type="number" name="RequisitionItems[${index}][totalpayable]" class="form-control js-totalpayable" step="0.0001" value="${escapeHtml(item.totalpayable ?? item.totalPayable ?? '')}" readonly required>
            </td>
        </tr>
    `;

    const populateRows = (form, items) => {
        const body = form.querySelector('[data-rfq-items-body]');
        const templateEl = form.querySelector('#rfq-tax-fields-template') || document.getElementById('rfq-tax-fields-template');

        if (!body || !templateEl) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            body.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No requisition items found for this RFQ.</td></tr>';
            recalculateTotals(form);
            return;
        }

        const taxTemplate = templateEl.innerHTML.trim();
        body.innerHTML = items.map((item, index) => buildRowMarkup(index, item, taxTemplate)).join('');

        items.forEach((item, index) => {
            const row = body.querySelectorAll('[data-rfq-item-row]')[index];
            if (!row) {
                return;
            }

            const fields = getRowFields(row);
            if (fields.discountPercentage) {
                const discountPercentage = item.discount_percentage ?? item.discountPercentage ?? '';
                fields.discountPercentage.value = discountPercentage === null || discountPercentage === '' ? '' : parseNumber(discountPercentage).toFixed(2);
            }
            if (fields.taxRuleId) {
                const taxRuleId = item.tax_rule_id ?? item.taxRuleId ?? '';
                if (taxRuleId !== null && taxRuleId !== '') {
                    fields.taxRuleId.value = String(taxRuleId);
                }
            }
            fields.taxType.value = item.tax_type ?? item.taxType ?? TAX.VAT;
            fields.taxRate.value = parseNumber(item.tax_rate ?? item.taxRate ?? TAX.DEFAULT_VAT_RATE).toFixed(2);
            fields.taxInclusive.value = String(Number(toOptionBoolean(item.is_tax_inclusive ?? item.isTaxInclusive)));

            const withholdingRate = item.withholding_tax_rate ?? item.withholdingTaxRate ?? '';
            fields.withholdingTaxRate.value = withholdingRate === null || withholdingRate === '' ? '' : parseNumber(withholdingRate).toFixed(2);

            recalculateRow(row);
        });

        recalculateTotals(form);
    };

    const populateExistingResponse = (form, data) => {
        if (!data || !Array.isArray(data.items)) {
            return;
        }

        const rows = Array.from(form.querySelectorAll('[data-rfq-item-row]'));
        const itemsByLineId = new Map(
            data.items.map((item) => [String(item.rfq_line_id ?? ''), item])
        );

        rows.forEach((row) => {
            const lineIdField = row.querySelector('input[name$="[rfq_line_id]"]');
            const quotedPriceField = row.querySelector('.js-quoted-price');
            const totalPayableField = row.querySelector('.js-totalpayable');
            const discountPercentageField = row.querySelector('.js-discount-percentage');
            const taxRuleIdField = row.querySelector('.js-tax-rule-id');
            const taxTypeField = row.querySelector('.js-tax-type');
            const taxRateField = row.querySelector('.js-tax-rate');
            const taxInclusiveField = row.querySelector('.js-tax-inclusive');
            const withholdingRateField = row.querySelector('.js-withholding-tax-rate');

            const matchedItem = itemsByLineId.get(String(lineIdField?.value ?? ''));
            if (!matchedItem) {
                return;
            }

            if (quotedPriceField) {
                quotedPriceField.value = parseNumber(matchedItem.quotedPrice).toFixed(4);
            }

            if (totalPayableField) {
                totalPayableField.value = parseNumber(matchedItem.totalPayable).toFixed(4);
            }

            if (discountPercentageField) {
                const discountPercentage = matchedItem.discountPercentage ?? matchedItem.discount_percentage ?? '';
                discountPercentageField.value = discountPercentage === null || discountPercentage === '' ? '' : parseNumber(discountPercentage).toFixed(2);
            }

            if (taxRuleIdField) {
                const taxRuleId = matchedItem.taxRuleId ?? matchedItem.tax_rule_id ?? '';
                if (taxRuleId !== null && taxRuleId !== '') {
                    taxRuleIdField.value = String(taxRuleId);
                }
            }

            if (taxTypeField) {
                taxTypeField.value = matchedItem.taxType ?? TAX.VAT;
            }

            if (taxRateField) {
                taxRateField.value = parseNumber(matchedItem.taxRate || TAX.DEFAULT_VAT_RATE).toFixed(2);
            }

            if (taxInclusiveField) {
                taxInclusiveField.value = String(Number(toOptionBoolean(matchedItem.isTaxInclusive)));
            }

            if (withholdingRateField) {
                withholdingRateField.value = matchedItem.withholdingTaxRate === null || matchedItem.withholdingTaxRate === ''
                    ? ''
                    : parseNumber(matchedItem.withholdingTaxRate).toFixed(2);
            }
        });

        if (data.header) {
            const durationField = form.querySelector('input[name="DurationDays"]');
            const currencyField = form.querySelector('select[name="Currency"]');

            if (durationField && data.header.durationDays) {
                durationField.value = data.header.durationDays;
            }

            if (currencyField && data.header.currency) {
                currencyField.value = data.header.currency;
            }
        }

        recalculateTotals(form);
    };

    const bindCreateForm = (form) => {
        const rfqSelect = form.querySelector('#rfq-code');
        const rfqNumberInput = form.querySelector('#rfq-number');
        const supplierSelect = form.querySelector('#supplier-select');
        const supplierIdInput = form.querySelector('#supplier-id');
        const alertBox = form.querySelector('#existing-response-alert');
        const suppliersUrlTemplate = form.dataset.suppliersUrlTemplate;
        const itemsUrlTemplate = form.dataset.itemsUrlTemplate;
        const existingUrl = form.dataset.findExistingUrl;

        if (!rfqSelect || !supplierSelect || !supplierIdInput || !itemsUrlTemplate || !suppliersUrlTemplate) {
            return;
        }

        const hasPreloadedItems = () => form.querySelectorAll('[data-rfq-item-row]').length > 0;

        const clearExistingState = () => {
            if (alertBox) {
                alertBox.classList.add('d-none');
            }
            setFormLocked(form, false);
            form.querySelectorAll('[data-lock-exempt]').forEach((element) => element.removeAttribute('disabled'));
        };

        const loadSuppliers = async (rfqId) => {
            supplierSelect.innerHTML = '<option value="">-- Select Supplier --</option>';

            if (!rfqId) {
                return;
            }

            try {
                const response = await fetch(suppliersUrlTemplate.replace('__RFQ_ID__', encodeURIComponent(rfqId)));
                const data = await response.json();

                if (!Array.isArray(data) || data.length === 0) {
                    supplierSelect.innerHTML = '<option value="">No suppliers available</option>';
                    return;
                }

                data.forEach((supplier) => {
                    const option = document.createElement('option');
                    option.value = supplier.Id;
                    option.textContent = supplier.SupplierName;
                    supplierSelect.appendChild(option);
                });

                if (supplierIdInput.value) {
                    supplierSelect.value = supplierIdInput.value;
                }
            } catch (error) {
                supplierSelect.innerHTML = '<option value="">Failed to load suppliers</option>';
            }
        };

        const loadItems = async (rfqId) => {
            if (!rfqId) {
                populateRows(form, []);
                return;
            }

            try {
                const response = await fetch(itemsUrlTemplate.replace('__RFQ_ID__', encodeURIComponent(rfqId)));
                const data = await response.json();
                populateRows(form, data.requisitionItems || []);
            } catch (error) {
                populateRows(form, []);
            }
        };

        const checkExistingResponse = async () => {
            const rfqId = rfqSelect.value;
            const supplierId = supplierIdInput.value;

            clearExistingState();

            if (!rfqId || !supplierId || !existingUrl) {
                recalculateTotals(form);
                return;
            }

            try {
                const query = new URLSearchParams({ rfqId, supplierId });
                const response = await fetch(`${existingUrl}?${query.toString()}`);
                const data = await response.json();

                if (!data || !data.exists) {
                    recalculateTotals(form);
                    return;
                }

                populateExistingResponse(form, data);
                if (alertBox) {
                    alertBox.classList.remove('d-none');
                }
                setFormLocked(form, true);
                form.querySelectorAll('[data-lock-exempt]').forEach((element) => element.removeAttribute('disabled'));
            } catch (error) {
                recalculateTotals(form);
            }
        };

        rfqSelect.addEventListener('change', async function () {
            const rfqId = this.value;
            const selectedOption = this.options[this.selectedIndex];

            if (rfqNumberInput) {
                rfqNumberInput.value = selectedOption ? selectedOption.textContent.trim() : '';
            }

            supplierIdInput.value = '';
            supplierSelect.value = '';
            clearExistingState();

            await Promise.all([loadSuppliers(rfqId), loadItems(rfqId)]);
        });

        supplierSelect.addEventListener('change', function () {
            supplierIdInput.value = this.value || '';
            checkExistingResponse();
        });

        if (rfqSelect.value) {
            const selectedOption = rfqSelect.options[rfqSelect.selectedIndex];
            if (rfqNumberInput) {
                rfqNumberInput.value = selectedOption ? selectedOption.textContent.trim() : '';
            }

            if (hasPreloadedItems()) {
                loadSuppliers(rfqSelect.value).then(() => {
                    recalculateTotals(form);
                });
            } else {
                Promise.all([loadSuppliers(rfqSelect.value), loadItems(rfqSelect.value)]).then(() => {
                    if (supplierIdInput.value) {
                        checkExistingResponse();
                    }
                });
            }
        }
    };

    const bindForm = (form) => {
        if (form.dataset.taxBound === '1') {
            return;
        }

        form.dataset.taxBound = '1';

        form.addEventListener('input', (event) => {
            if (event.target.closest('[data-rfq-item-row]')) {
                recalculateTotals(form);
            }
        });

        form.addEventListener('change', (event) => {
            if (event.target.closest('[data-rfq-item-row]')) {
                recalculateTotals(form);
            }
        });

        bindCreateForm(form);
        recalculateTotals(form);
    };

    const init = () => {
        document.querySelectorAll('[data-rfq-tax-form]').forEach(bindForm);
    };

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('partial:loaded', init);
})();
