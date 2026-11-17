// Envoi d'une vidéo par morceaux, avec reprise et nouvelles tentatives.
//
// Le fichier est découpé en morceaux de la taille indiquée par le serveur (5 Mo). Chaque morceau est
// envoyé séparément : une coupure réseau ne fait recommencer que le morceau en cours, et un envoi
// interrompu peut reprendre où il s'était arrêté (le serveur dit quels morceaux il a déjà reçus).

const wait = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

export class UploadError extends Error {
    constructor(message, { retryable = false } = {}) {
        super(message);
        this.name = 'UploadError';
        this.retryable = retryable;
    }
}

async function readError(response) {
    try {
        const body = await response.json();
        if (body && typeof body.message === 'string') {
            return body.message;
        }
    } catch {
        // Réponse sans JSON : on retombe sur le message générique.
    }

    return `Erreur ${response.status}`;
}

/**
 * @param {object} options
 * @param {File|Blob & {name: string, lastModified?: number}} options.file
 * @param {'title'|'episode'} options.kind
 * @param {string} options.targetId
 * @param {{start: string, status: (id: string) => string, chunk: (id: string, index: number) => string, complete: (id: string) => string}} options.endpoints
 * @param {string} options.csrf
 * @param {typeof fetch} [options.fetchImpl]
 * @param {(uploaded: number, total: number) => void} [options.onProgress]
 * @param {{get: (key: string) => string|null, set: (key: string, value: string) => void, remove: (key: string) => void}} [options.memory]
 * @param {number} [options.retries] nombre de nouvelles tentatives par morceau
 * @param {(milliseconds: number) => Promise<void>} [options.sleep]
 */
export async function uploadFile({
    file,
    kind,
    targetId,
    endpoints,
    csrf,
    fetchImpl = (...args) => fetch(...args),
    onProgress = () => {},
    memory = { get: () => null, set: () => {}, remove: () => {} },
    retries = 3,
    sleep = wait,
}) {
    const headers = { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' };
    const key = `homeflix-upload:${kind}:${targetId}:${file.name}:${file.size}:${file.lastModified ?? 0}`;

    // 1. Reprendre un envoi interrompu si le serveur le connaît encore, sinon en commencer un nouveau.
    let session = null;
    let received = new Set();
    const previous = memory.get(key);

    if (previous) {
        const status = await fetchImpl(endpoints.status(previous), { headers });

        if (status.ok) {
            const body = await status.json();
            received = new Set(body.received);
            session = { upload_id: previous, total_chunks: body.total_chunks, chunk_size: null };
        } else {
            memory.remove(key);
        }
    }

    if (session === null) {
        const response = await fetchImpl(endpoints.start, {
            method: 'POST',
            headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify({
                kind,
                target_id: targetId,
                filename: file.name,
                size: file.size,
                total_chunks: Math.ceil(file.size / 5242880),
            }),
        });

        if (!response.ok) {
            throw new UploadError(await readError(response));
        }

        session = await response.json();
        received = new Set();
        memory.set(key, session.upload_id);
    }

    // La taille d'un morceau est celle du serveur ; en reprise, on la déduit du nombre de morceaux.
    const chunkSize = session.chunk_size ?? 5242880;
    const total = session.total_chunks;
    let uploaded = [...received].reduce((sum, index) => sum + Math.min(chunkSize, file.size - index * chunkSize), 0);
    onProgress(uploaded, file.size);

    // 2. Envoyer les morceaux manquants, un par un, avec de nouvelles tentatives.
    for (let index = 0; index < total; index++) {
        if (received.has(index)) {
            continue;
        }

        const blob = file.slice(index * chunkSize, Math.min(file.size, (index + 1) * chunkSize));
        await sendChunk({ blob, index, uploadId: session.upload_id, endpoints, headers, fetchImpl, retries, sleep });

        uploaded += blob.size;
        onProgress(uploaded, file.size);
    }

    // 3. Assembler et lancer le transcodage.
    const done = await fetchImpl(endpoints.complete(session.upload_id), { method: 'POST', headers });

    if (!done.ok) {
        memory.remove(key);
        throw new UploadError(await readError(done));
    }

    memory.remove(key);

    return (await done.json()).media_id;
}

async function sendChunk({ blob, index, uploadId, endpoints, headers, fetchImpl, retries, sleep }) {
    let lastMessage = '';

    for (let attempt = 0; attempt <= retries; attempt++) {
        if (attempt > 0) {
            await sleep(Math.min(8000, 500 * 2 ** (attempt - 1)));
        }

        let response;

        try {
            response = await fetchImpl(endpoints.chunk(uploadId, index), {
                method: 'PUT',
                headers: { ...headers, 'Content-Type': 'application/octet-stream' },
                body: blob,
            });
        } catch {
            lastMessage = 'Connexion interrompue.';
            continue;
        }

        if (response.ok) {
            return;
        }

        lastMessage = await readError(response);

        // Une erreur du client (taille, numéro, envoi inconnu) ne se règle pas en réessayant. Une erreur du serveur, si.
        if (response.status >= 400 && response.status < 500 && response.status !== 429 && response.status !== 419) {
            throw new UploadError(lastMessage);
        }
    }

    throw new UploadError(`Le morceau ${index + 1} n'a pas pu être envoyé : ${lastMessage}`, { retryable: true });
}

/** Branche le formulaire de la page d'envoi. */
export function setUp(root) {
    const form = root.querySelector('[data-upload-form]');

    if (!form) {
        return;
    }

    const input = form.querySelector('input[type=file]');
    const button = form.querySelector('button[type=submit]');
    const progress = root.querySelector('[data-upload-progress]');
    const message = root.querySelector('[data-upload-message]');
    const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') ?? '';

    const show = (text, isError = false) => {
        message.textContent = text;
        message.className = isError ? 'mt-3 text-sm text-red-700' : 'mt-3 text-sm text-gray-700';
    };

    const memory = {
        get: (key) => { try { return localStorage.getItem(key); } catch { return null; } },
        set: (key, value) => { try { localStorage.setItem(key, value); } catch { /* stockage indisponible : pas de reprise */ } },
        remove: (key) => { try { localStorage.removeItem(key); } catch { /* idem */ } },
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const file = input.files?.[0];

        if (!file) {
            show('Choisissez un fichier vidéo.', true);

            return;
        }

        button.disabled = true;
        input.disabled = true;
        progress.hidden = false;
        show('Envoi en cours… Ne fermez pas cette page.');

        try {
            await uploadFile({
                file,
                kind: form.dataset.kind,
                targetId: form.dataset.target,
                csrf,
                memory,
                endpoints: {
                    start: form.dataset.startUrl,
                    status: (id) => form.dataset.statusUrl.replace('__ID__', id),
                    chunk: (id, index) => form.dataset.chunkUrl.replace('__ID__', id).replace('__INDEX__', String(index)),
                    complete: (id) => form.dataset.completeUrl.replace('__ID__', id),
                },
                onProgress: (done, total) => {
                    progress.value = Math.round((done / total) * 100);
                    show(`Envoi en cours : ${progress.value} %. Ne fermez pas cette page.`);
                },
            });

            show('Envoi terminé. La conversion démarre…');
            window.location.reload();
        } catch (error) {
            show(error.message || 'L\'envoi a échoué.', true);
            button.disabled = false;
            input.disabled = false;
        }
    });
}

if (typeof document !== 'undefined') {
    setUp(document);
}
