

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('[data-regional-money]').forEach((element) => {
		const amount = Number(element.dataset.amount);
		const currency = element.dataset.currency;
		const locale = element.dataset.locale.replace(/_/g, '-');

		try {
			element.textContent = new Intl.NumberFormat(locale, {
				style: 'currency',
				currency,
			}).format(amount);
		} catch {
			element.textContent = new Intl.NumberFormat('en', {
				style: 'currency',
				currency,
			}).format(amount);
		}
	});

	document.querySelectorAll('[data-regional-date]').forEach((element) => {
		const locale = element.dataset.locale.replace(/_/g, '-');
		const dateOnly = element.dataset.dateOnly === 'true';
		const value = dateOnly ? `${element.dataset.value}T12:00:00Z` : element.dataset.value;
        const date = new Date(value);

		if (Number.isNaN(date.getTime())) {
			return;
		}

		try {
			element.textContent = new Intl.DateTimeFormat(locale, {
				dateStyle: 'medium',
				...(dateOnly ? {} : { timeStyle: 'short' }),
				timeZone: element.dataset.timeZone,
			}).format(date);
		} catch {
			element.textContent = new Intl.DateTimeFormat('en', {
				dateStyle: 'medium',
				...(dateOnly ? {} : { timeStyle: 'short' }),
				timeZone: 'UTC',
			}).format(date);
		}
	});

	document.querySelectorAll('[data-open-dialog]').forEach((button) => {
		button.addEventListener('click', () => {
			document.getElementById(button.dataset.openDialog)?.showModal();
		});
	});

	document.querySelectorAll('[data-close-dialog]').forEach((button) => {
		button.addEventListener('click', () => button.closest('dialog')?.close());
	});

	document.querySelectorAll('[data-edit-transaction]').forEach((button) => {
		button.addEventListener('click', () => {
			const dialog = document.getElementById('edit-dialog');
			const form = document.getElementById('edit-transaction-form');
			const expenseFields = dialog.querySelector('[data-edit-expense-fields]');
			const invoiceFields = dialog.querySelector('[data-edit-invoice-fields]');
			const isExpense = button.dataset.kind === 'expense';

			form.action = button.dataset.updateUrl;
			expenseFields.hidden = !isExpense;
			invoiceFields.hidden = isExpense;
			expenseFields.querySelectorAll('input, select').forEach((field) => {
				field.disabled = !isExpense;
			});
			invoiceFields.querySelectorAll('input, select').forEach((field) => {
				field.disabled = isExpense;
			});

			const prefix = isExpense ? 'expense' : 'invoice';
			document.getElementById(`edit-${prefix}-title`).value = button.dataset.title;
			document.getElementById(`edit-${prefix}-detail`).value = button.dataset.detail;
			document.getElementById(`edit-${prefix}-amount`).value = button.dataset.amount;
			document.getElementById(`edit-${prefix}-date`).value = button.dataset.date;
			document.getElementById(`edit-${prefix}-status`).value = button.dataset.status;
			dialog.showModal();
		});
	});

	document.querySelectorAll('dialog').forEach((dialog) => {
		dialog.addEventListener('click', (event) => {
			if (event.target === dialog) {
				dialog.close();
			}
		});
	});
});
