/**
 * The memory search forms' chained lists (front page, Info Page, category pages).
 *
 * One job: picking a state loads its councils, and picking councils loads their merged
 * councils (when asked for), lodges and camps. The lists come from the same admin-ajax
 * actions the old site used (search_councils, search_merged_councils, search_lodges,
 * search_camps), fetched with `fetch`.
 *
 * Each list is a Tom Select box. Tom Select's stylesheet is never loaded: every part of a
 * box gets its look from the Tailwind classes below, through Tom Select's class options.
 *
 * The form element carries the address and the nonce:
 *   <form x-data="memorySearch" data-ajax-url="..." data-nonce="...">
 */

import TomSelect from 'tom-select';

/* The look of one list, part by part. */
const LOOK = {
	// The box and its dropdown sit in one positioned wrapper.
	wrapper: 'relative w-full',
	// The box: white, one grey line, chips and the typing field in a wrapping row.
	control:
		'flex min-h-[calc(1.5em+0.75rem+2px)] w-full cursor-text flex-wrap items-center gap-1 rounded border border-black/[.33] bg-white px-1.5 py-1 text-base leading-normal text-ink focus-within:border-black',
	// The typing field inside the box.
	input: 'min-w-16 flex-1 border-0 bg-transparent p-0 text-base leading-normal text-ink outline-0 focus:ring-0 focus:outline-0',
	// The dropdown: a white panel with one line and a shadow, right under the box.
	dropdown: 'absolute top-full left-0 z-50 w-full border border-black/[.33] bg-white shadow',
	dropdownContent: 'max-h-52 overflow-y-auto',
	// One choice in the dropdown; Tom Select marks the one under the pointer or the keys "active".
	option: 'cursor-pointer px-1.5 py-1.5 text-base leading-normal break-words text-ink hover:bg-light [&.active]:bg-blue [&.active]:text-white',
	// One picked choice: a small grey chip with an x in front.
	item: 'inline-flex max-w-full items-center rounded border border-black/[.33] bg-black/10 px-1.5 text-sm leading-normal break-words text-ink',
	remove: 'order-first mr-1 inline-flex min-h-6 min-w-6 cursor-pointer items-center justify-center font-bold text-black/40 hover:text-ink',
	// "No results found".
	empty: 'px-1.5 py-1.5 text-base leading-normal text-grey',
};

/* The original <select> stays in the form (it is what gets sent) but is not shown. */
const HIDDEN_SELECT = 'sr-only';

/* Each list: its key here, the id of its <select>, and how many choices may be picked. */
const LISTS = [
	['cat', 'cat', 1],
	['state', 'select_state', 3],
	['council', 'select_council', 3],
	['merged', 'select_merged_council', null],
	['lodge', 'select_lodge', 5],
	['camp', 'select_camp', 3],
];

/**
 * Turns a <select multiple> into a Tom Select box with type-to-search.
 *
 * @param {HTMLSelectElement} select   The field.
 * @param {number|null}       maxItems How many choices may be picked (null: any number).
 * @return {TomSelect} The box.
 */
function makeList(select, maxItems) {
	// A stored list of options can arrive wrapped in other elements; the box only reads options
	// that sit directly in the list, so lift them out and drop the wrappers.
	const options = [...select.querySelectorAll('option')];
	select.replaceChildren(...options);

	// The original list stays on the page for screen readers and for sending the form; it takes
	// its name from the field's label.
	const label = select.id ? document.querySelector(`label[for="${select.id}"]`) : null;
	if (label && !select.hasAttribute('aria-label')) {
		select.setAttribute('aria-label', label.textContent.trim());
	}

	const list = new TomSelect(select, {
		mode: 'multi',
		maxItems,
		// The list closes after each pick, as the old select2 boxes did; an open list would also
		// cover the spinner that shows under it while the next list loads.
		closeAfterSelect: true,
		maxOptions: null,
		hideSelected: true,
		highlight: false,
		plugins: {
			remove_button: { className: LOOK.remove, title: 'Remove' },
		},
		wrapperClass: LOOK.wrapper,
		controlClass: LOOK.control,
		dropdownClass: LOOK.dropdown,
		dropdownContentClass: LOOK.dropdownContent,
		optionClass: LOOK.option,
		itemClass: LOOK.item,
		controlInput: `<input type="text" autocomplete="off" size="1" class="${LOOK.input}" />`,
		render: {
			option: (data, escape) => `<div>${escape(data.text)}</div>`,
			item: (data, escape) => `<div><span>${escape(data.text)}</span></div>`,
			no_results: () => `<div class="${LOOK.empty}">No results found</div>`,
		},
	});
	select.classList.add(HIDDEN_SELECT);
	return list;
}

/**
 * Empties a list and gives it new choices.
 *
 * @param {TomSelect} list    The box.
 * @param {Array}     options The new choices: { value, text }.
 */
function fill(list, options) {
	list.clear(true);
	list.clearOptions();
	list.addOptions(options);
	list.refreshOptions(false);
}

/**
 * Reads the <option> elements the server sends back: sorted by name, each value once.
 *
 * @param {string} html The options as HTML.
 * @return {Array} The choices: { value, text }.
 */
function optionsFrom(html) {
	const holder = document.createElement('select');
	holder.innerHTML = html || '';
	const seen = new Set();
	return [...holder.options]
		.map((option) => ({ value: option.value, text: option.textContent.trim() }))
		.sort((a, b) => (a.text > b.text ? 1 : -1))
		.filter((option) => {
			if (option.value === '' || seen.has(option.value)) {
				return false;
			}
			seen.add(option.value);
			return true;
		});
}

/**
 * Asks admin-ajax for a list, the way the old script did (a form post marked as an ajax call).
 *
 * @param {string} url    The admin-ajax address.
 * @param {Object} fields The fields to send; an array is sent as name[].
 * @return {Promise<Array>} The choices: { value, text }.
 */
async function ask(url, fields) {
	const body = new URLSearchParams();
	for (const [name, value] of Object.entries(fields)) {
		if (Array.isArray(value)) {
			value.forEach((one) => body.append(`${name}[]`, one));
		} else {
			body.append(name, value);
		}
	}
	const response = await fetch(url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'X-Requested-With': 'XMLHttpRequest' },
		body,
	});
	if (!response.ok) {
		throw new Error(`${fields.action}: ${response.status}`);
	}
	const answer = await response.json();
	return optionsFrom(answer.state);
}

/**
 * Registers the search form's Alpine component.
 *
 * @param {Object} Alpine Alpine.js.
 */
export default function registerSearch(Alpine) {
	Alpine.data('memorySearch', () => {
		// Kept out of Alpine's watched data: the Tom Select boxes, the choices named in the
		// page address, and a counter per list so a late answer never replaces a newer one.
		const lists = {};
		const preset = { state: [], council: [], lodge: [], camp: [] };
		const turn = { council: 0, merged: 0, lodge: 0, camp: 0 };
		let ajaxUrl = '';
		let nonce = '';

		return {
			// The state (and category) boxes are built.
			ready: false,
			// "Show Merged Councils?" is ticked.
			showMerged: false,
			// Which of the lower fields are showing, and which are waiting for their list.
			shown: { council: false, merged: false, lodge: false, camp: false },
			loading: { council: false, merged: false, lodge: false, camp: false },

			init() {
				const form = this.$el;
				ajaxUrl = form.dataset.ajaxUrl || '';
				nonce = form.dataset.nonce || '';

				const address = new URLSearchParams(window.location.search);
				for (const name of Object.keys(preset)) {
					preset[name] = (address.get(name) || '').split(',').filter(Boolean);
				}

				for (const [key, id, maxItems] of LISTS) {
					const select = form.querySelector(`#${id}`);
					if (select) {
						lists[key] = makeList(select, maxItems);
					}
				}
				if (!lists.state || !lists.council) {
					return;
				}

				if (lists.cat && address.get('cat')) {
					lists.cat.setValue(address.get('cat'), true);
				}

				lists.state.on('change', () => this.loadCouncils());
				lists.council.on('change', () => this.councilsChanged());
				if (lists.merged) {
					lists.merged.on('change', () => this.loadLodgesAndCamps());
				}

				this.ready = true;

				if (preset.state.length) {
					lists.state.setValue(preset.state, true);
					this.loadCouncils();
				}
			},

			destroy() {
				for (const key of Object.keys(lists)) {
					lists[key].destroy();
					delete lists[key];
				}
			},

			/*
			 * A state was picked or removed: load the councils of the picked states. The spinner
			 * shows in the council field's place while they load, so the wait never looks like
			 * nothing happened.
			 */
			async loadCouncils() {
				const states = [...lists.state.getValue()];
				const mine = ++turn.council;

				this.shown.council = false;
				this.shown.lodge = false;
				this.shown.camp = false;
				this.loading.lodge = false;
				this.loading.camp = false;
				this.loading.council = states.length > 0;

				let options = [];
				if (states.length) {
					try {
						options = await ask(ajaxUrl, { action: 'search_councils', selected_states: states, nonce });
					} catch (error) {
						console.error(error);
					}
				}
				if (mine !== turn.council) {
					return;
				}

				this.loading.council = false;
				fill(lists.council, options);
				if (options.length) {
					lists.council.setValue(preset.council, true);
					this.shown.council = true;
				}
				this.councilsChanged();
			},

			/* The picked councils changed: load their merged councils (when asked for), lodges and camps. */
			async councilsChanged() {
				const councils = [...lists.council.getValue()];
				const mine = ++turn.merged;

				if (lists.merged) {
					fill(lists.merged, []);
				}
				this.loadLodgesAndCamps();

				if (!lists.merged || !this.showMerged || !councils.length) {
					this.shown.merged = false;
					this.loading.merged = false;
					return;
				}

				this.loading.merged = true;
				let options = [];
				try {
					options = await ask(ajaxUrl, { action: 'search_merged_councils', selected_councils: councils, nonce });
				} catch (error) {
					console.error(error);
				}
				if (mine !== turn.merged) {
					return;
				}

				fill(lists.merged, options);
				this.shown.merged = options.length > 0;
				this.loading.merged = false;
			},

			/* "Show Merged Councils?" was ticked or cleared. */
			mergedToggled() {
				if (!this.showMerged) {
					this.shown.merged = false;
				}
				this.councilsChanged();
			},

			/* Lodges and camps of the picked councils (and of the picked merged councils, when shown). */
			loadLodgesAndCamps() {
				const councils = [...lists.council.getValue()];
				let selected = councils;
				if (this.showMerged && lists.merged) {
					// The old script sent one comma-separated text here; the server splits it.
					selected = `${councils.join(',')},${[...lists.merged.getValue()].join(',')}`;
				}
				this.loadList('lodge', 'search_lodges', selected, councils.length > 0);
				this.loadList('camp', 'search_camps', selected, councils.length > 0);
			},

			/**
			 * Loads one of the two last lists.
			 *
			 * @param {string}       key      'lodge' or 'camp'.
			 * @param {string}       action   The admin-ajax action.
			 * @param {Array|string} selected The councils to send.
			 * @param {boolean}      any      Whether any council is picked at all.
			 */
			async loadList(key, action, selected, any) {
				if (!lists[key]) {
					return;
				}
				const mine = ++turn[key];

				fill(lists[key], []);
				this.shown[key] = false;
				if (!any) {
					this.loading[key] = false;
					return;
				}

				this.loading[key] = true;
				let options = [];
				try {
					options = await ask(ajaxUrl, { action, selected_councils: selected, nonce });
				} catch (error) {
					console.error(error);
				}
				if (mine !== turn[key]) {
					return;
				}

				fill(lists[key], options);
				if (options.length) {
					lists[key].setValue(preset[key], true);
					this.shown[key] = true;
				}
				this.loading[key] = false;
			},
		};
	});
}
