/**
 * The media library, as Alpine data: the list of a user's files on the My Account
 * page's Media tab, and the same list inside the "choose a file" window the
 * rich-text editor opens (theme/template-parts/media/).
 *
 * One job: talk to WordPress's own REST API (wp/v2/media) with fetch, and hold what
 * the template shows. It lists, searches, uploads (file box or drag and drop), edits a
 * file's details, copies its address and deletes it. WordPress checks every request
 * against the logged-in user; nothing here decides who may do what.
 *
 * The template hands over what it needs as data attributes on the element that
 * carries x-data="mediaLibrary":
 *   data-endpoint  the REST address of the media list
 *   data-nonce     the REST nonce of the logged-in user
 *   data-user      the user's id
 *   data-all       "1" when the list shows everyone's files (administrators)
 *   data-mode      "pick" inside the chooser window, otherwise "manage"
 *   data-messages  the sentences this script says, already translated (JSON)
 *
 * The chooser: any script asks for a file with
 *   window.dispatchEvent(new CustomEvent('sm-media-pick'))
 * and gets the answer as the window event `sm-media-picked`, with
 *   detail { id, url, alt, title, mime }.
 */

const PER_PAGE = 24;
const FIELDS =
	'id,title,alt_text,caption,source_url,mime_type,media_type,media_details';

/** The plain text of a piece of HTML the REST API sent (titles and captions arrive rendered). */
function plainText(html) {
	return new DOMParser()
		.parseFromString(String(html || ''), 'text/html')
		.body.textContent.trim();
}

/** A raw or rendered REST text field as plain text: the raw value when the API gave one. */
function fieldText(field) {
	if (field && typeof field.raw === 'string') {
		return field.raw;
	}
	return plainText(field && field.rendered);
}

/** A short label for a file that has no picture: its ending in capitals ("PDF", "MP4"). */
function fileKind(url, mime) {
	const ending = /\.([a-z0-9]{1,5})(?:$|[?#])/i.exec(String(url || ''));
	if (ending) {
		return ending[1].toUpperCase();
	}
	return String(mime || '')
		.split('/')
		.pop()
		.toUpperCase();
}

/** One file as the template shows it, from what the REST API sent. */
function toCard(raw) {
	const image = raw.media_type === 'image';
	const sizes = (raw.media_details && raw.media_details.sizes) || {};
	const small = sizes.medium || sizes.thumbnail;
	return {
		id: raw.id,
		title: fieldText(raw.title),
		alt: raw.alt_text || '',
		caption: fieldText(raw.caption),
		url: raw.source_url,
		mime: raw.mime_type,
		image,
		thumb: image ? (small && small.source_url) || raw.source_url : '',
		kind: fileKind(raw.source_url, raw.mime_type),
	};
}

function mediaLibrary() {
	// Kept out of Alpine's data on purpose: an element inside reactive data cannot be focused.
	let opener = null;

	return {
		// What the template handed over.
		endpoint: '',
		nonce: '',
		user: 0,
		all: false,
		pick: false,
		text: {},

		// The list.
		items: [],
		page: 0,
		pages: 1,
		total: 0,
		search: '',
		loading: false,
		loaded: false,

		// Uploading.
		uploading: false,
		uploadDone: 0,
		uploadCount: 0,
		dragging: false,

		// What is said to the user: `status` is read out politely, `error` at once.
		status: '',
		error: '',

		// The open file's details.
		current: 0,
		form: { title: '', alt: '', caption: '' },
		busy: false,
		confirming: false,

		init() {
			const given = this.$el.dataset;
			this.endpoint = given.endpoint || '';
			this.nonce = given.nonce || '';
			this.user = Number(given.user) || 0;
			this.all = given.all === '1';
			this.pick = given.mode === 'pick';
			try {
				this.text = JSON.parse(given.messages || '{}');
			} catch {
				this.text = {};
			}

			// The first page of files is fetched when the library is first seen (its tab is
			// opened, or the chooser window is), not on every page that prints it.
			const seen = new IntersectionObserver((entries) => {
				if (entries.some((entry) => entry.isIntersecting)) {
					seen.disconnect();
					this.load(true);
				}
			});
			seen.observe(this.$el);
		},

		/** The file whose details are open, or undefined. */
		get selected() {
			return this.items.find((item) => item.id === this.current);
		},

		get more() {
			return this.loaded && this.page < this.pages;
		},

		/** One of the template's sentences, with %1$s, %2$s ... filled in. */
		say(key, ...values) {
			return String(this.text[key] || '').replace(
				/%(\d+)\$s/g,
				(whole, n) => {
					const value = values[Number(n) - 1];
					return value === undefined ? '' : String(value);
				}
			);
		},

		/** The REST address of the list, or of one file, with query parameters. */
		address(id, params = {}) {
			const url = new URL(this.endpoint, window.location.href);
			if (id) {
				// Sites without pretty addresses carry the route in a parameter.
				if (url.searchParams.has('rest_route')) {
					url.searchParams.set(
						'rest_route',
						`${url.searchParams.get('rest_route')}/${id}`
					);
				} else {
					url.pathname = `${url.pathname.replace(/\/$/, '')}/${id}`;
				}
			}
			for (const [name, value] of Object.entries(params)) {
				url.searchParams.set(name, value);
			}
			return url;
		},

		/** A sentence a person can read for a request that failed. */
		problem(status, body) {
			if (body && typeof body.message === 'string' && body.message) {
				return plainText(body.message);
			}
			if (status === 413) {
				return this.say('tooLarge');
			}
			if (status === 401 || status === 403) {
				return this.say('notAllowed');
			}
			return this.say('failed', status);
		},

		/** One REST request as the logged-in user. Throws an Error with a readable message. */
		async request(url, options = {}) {
			let response;
			try {
				response = await fetch(url, {
					credentials: 'same-origin',
					...options,
					headers: {
						'X-WP-Nonce': this.nonce,
						...(options.headers || {}),
					},
				});
			} catch {
				throw new Error(this.say('offline'));
			}
			let body = null;
			try {
				body = await response.json();
			} catch {
				body = null;
			}
			if (!response.ok) {
				throw new Error(this.problem(response.status, body));
			}
			return { body, response };
		},

		/** Fetches the next page of files; `fresh` starts again from the first page. */
		async load(fresh = false) {
			if (this.loading) {
				return;
			}
			if (fresh) {
				this.items = [];
				this.page = 0;
				this.pages = 1;
				this.current = 0;
			}
			if (this.page >= this.pages) {
				return;
			}
			this.loading = true;
			this.error = '';
			this.status = this.say('loading');
			const params = {
				per_page: PER_PAGE,
				page: this.page + 1,
				orderby: 'date',
				order: 'desc',
				_fields: FIELDS,
			};
			if (!this.all) {
				params.author = this.user;
			}
			if (this.search.trim()) {
				params.search = this.search.trim();
			}
			try {
				const { body, response } = await this.request(
					this.address(0, params)
				);
				const known = new Set(this.items.map((item) => item.id));
				this.items.push(
					...body.map(toCard).filter((item) => !known.has(item.id))
				);
				this.page += 1;
				this.pages =
					Number(response.headers.get('X-WP-TotalPages')) ||
					this.page;
				this.total =
					Number(response.headers.get('X-WP-Total')) ||
					this.items.length;
				this.status = this.items.length
					? this.say('showing', this.items.length, this.total)
					: this.say('none');
			} catch (e) {
				this.error = e.message;
			} finally {
				this.loading = false;
				this.loaded = true;
			}
		},

		/** Sends the chosen or dropped files, one after another, newest first in the list. */
		async upload(files) {
			const list = [...(files || [])];
			if (!list.length || this.uploading) {
				return;
			}
			this.uploading = true;
			this.error = '';
			this.uploadCount = list.length;
			this.uploadDone = 0;
			const failed = [];
			for (const file of list) {
				this.status = this.say(
					'uploading',
					this.uploadDone + 1,
					this.uploadCount,
					file.name
				);
				const data = new FormData();
				data.append('file', file, file.name);
				try {
					const { body } = await this.request(
						this.address(0, { _fields: FIELDS }),
						{
							method: 'POST',
							body: data,
						}
					);
					this.items.unshift(toCard(body));
					this.total += 1;
				} catch (e) {
					failed.push(`${file.name}: ${e.message}`);
				}
				this.uploadDone += 1;
			}
			this.uploading = false;
			this.status = this.say(
				'uploaded',
				this.uploadCount - failed.length,
				this.uploadCount
			);
			this.error = failed.join(' ');
		},

		/** The file box changed: upload what was chosen and empty the box for the next time. */
		chosen(input) {
			this.upload(input.files);
			input.value = '';
		},

		dropped(event) {
			this.dragging = false;
			this.upload(event.dataTransfer ? event.dataTransfer.files : []);
		},

		/** A card was pressed: hand the file to whoever asked (chooser) or open its details. */
		press(item, button) {
			if (this.pick) {
				this.choose(item);
			} else {
				this.open(item, button);
			}
		},

		choose(item) {
			window.dispatchEvent(
				new CustomEvent('sm-media-picked', {
					detail: {
						id: item.id,
						url: item.url,
						alt: item.alt,
						title: item.title,
						mime: item.mime,
					},
				})
			);
		},

		/** Opens a file's details. The words as typed (not as displayed) are fetched when WordPress allows it. */
		async open(item, button) {
			opener = button || null;
			this.current = item.id;
			this.form = {
				title: item.title,
				alt: item.alt,
				caption: item.caption,
			};
			this.confirming = false;
			this.error = '';
			this.busy = true;
			this.$nextTick(() => {
				if (this.$refs.details) {
					this.$refs.details.focus();
				}
			});
			try {
				const { body } = await this.request(
					this.address(item.id, { context: 'edit', _fields: FIELDS })
				);
				if (this.current === item.id) {
					const typed = toCard(body);
					this.form = {
						title: typed.title,
						alt: typed.alt,
						caption: typed.caption,
					};
				}
			} catch {
				// Not allowed to read it for editing: the displayed words stay in the boxes.
			} finally {
				this.busy = false;
			}
		},

		close() {
			this.current = 0;
			this.confirming = false;
			this.$nextTick(() => {
				if (opener && opener.isConnected) {
					opener.focus();
				} else if (this.$refs.list) {
					this.$refs.list.focus();
				}
				opener = null;
			});
		},

		async save() {
			const item = this.selected;
			if (!item || this.busy) {
				return;
			}
			this.busy = true;
			this.error = '';
			try {
				const { body } = await this.request(
					this.address(item.id, { _fields: FIELDS }),
					{
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({
							title: this.form.title,
							alt_text: this.form.alt,
							caption: this.form.caption,
						}),
					}
				);
				Object.assign(item, toCard(body));
				this.status = this.say('saved', item.title);
			} catch (e) {
				this.error = e.message;
			} finally {
				this.busy = false;
			}
		},

		/** Deletes the open file for good (the template asks "are you sure" first, in the page). */
		async remove() {
			const item = this.selected;
			if (!item || this.busy) {
				return;
			}
			this.busy = true;
			this.error = '';
			try {
				await this.request(this.address(item.id, { force: 'true' }), {
					method: 'DELETE',
				});
				this.items = this.items.filter((other) => other.id !== item.id);
				this.total = Math.max(0, this.total - 1);
				this.status = this.say('deleted', item.title);
				opener = null;
				this.close();
			} catch (e) {
				this.error = e.message;
				this.confirming = false;
			} finally {
				this.busy = false;
			}
		},

		/** Copies the open file's address; where the browser refuses, the address is selected instead. */
		async copy() {
			const item = this.selected;
			if (!item) {
				return;
			}
			try {
				await navigator.clipboard.writeText(item.url);
				this.status = this.say('copied');
			} catch {
				if (this.$refs.address) {
					this.$refs.address.focus();
					this.$refs.address.select();
				}
				this.status = this.say('copyByHand');
			}
		},
	};
}

export default function registerMedia(Alpine) {
	Alpine.data('mediaLibrary', mediaLibrary);
}
