// Tests du script d'envoi, sans navigateur : `node --test tests/js`.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { UploadError, uploadFile } from '../../resources/js/media-upload.js';

const MB = 1024 * 1024;
const CHUNK = 5 * MB;

const endpoints = {
    start: '/start',
    status: (id) => `/status/${id}`,
    chunk: (id, index) => `/chunk/${id}/${index}`,
    complete: (id) => `/complete/${id}`,
};

const fileOf = (size, name = 'film.mp4') => {
    const blob = new Blob([new Uint8Array(size)]);
    return Object.assign(blob, { name, lastModified: 1 });
};

const json = (body, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

/** Faux serveur qui enregistre les appels. */
function server({ failChunk = {}, startStatus = 201, completeStatus = 201, statusReply = null } = {}) {
    const calls = [];
    const attempts = {};
    const fetchImpl = async (url, options = {}) => {
        calls.push({ url, method: options.method ?? 'GET', size: options.body?.size ?? null, headers: options.headers });
        if (url === '/start') return startStatus === 201 ? json({ upload_id: 'u1', chunk_size: CHUNK, total_chunks: Math.ceil(JSON.parse(options.body).size / CHUNK) }, 201) : json({ message: 'Format non accepté.' }, startStatus);
        if (url.startsWith('/status/')) return statusReply ?? json({ message: 'Envoi inconnu' }, 404);
        if (url.startsWith('/chunk/')) {
            const index = Number(url.split('/').pop());
            attempts[index] = (attempts[index] ?? 0) + 1;
            const plan = failChunk[index];
            if (plan && attempts[index] <= plan.times) {
                if (plan.network) throw new TypeError('network down');
                return json({ message: plan.message ?? 'Erreur serveur' }, plan.status ?? 500);
            }
            return json({ received: index });
        }
        if (url.startsWith('/complete/')) return completeStatus === 201 ? json({ media_id: 'm1' }, 201) : json({ message: 'Le fichier n\'est pas une vidéo valide.' }, completeStatus);
        throw new Error(`appel inattendu ${url}`);
    };
    return { calls, attempts, fetchImpl };
}

const run = (file, srv, extra = {}) => uploadFile({ file, kind: 'title', targetId: 't1', endpoints, csrf: 'jeton', fetchImpl: srv.fetchImpl, sleep: async () => {}, ...extra });

test('découpe le fichier en morceaux de 5 Mo, dans l\'ordre, puis assemble', async () => {
    const srv = server();
    const mediaId = await run(fileOf(12 * MB), srv);

    assert.equal(mediaId, 'm1');
    const chunks = srv.calls.filter((c) => c.url.startsWith('/chunk/'));
    assert.deepEqual(chunks.map((c) => c.url), ['/chunk/u1/0', '/chunk/u1/1', '/chunk/u1/2']);
    assert.deepEqual(chunks.map((c) => c.size), [CHUNK, CHUNK, 2 * MB]);
    assert.equal(srv.calls.at(-1).url, '/complete/u1');
});

test('annonce la taille et le nombre de morceaux au démarrage', async () => {
    let body;
    const srv = server();
    const inner = srv.fetchImpl;
    srv.fetchImpl = async (url, options) => { if (url === '/start') body = JSON.parse(options.body); return inner(url, options); };

    await run(fileOf(12 * MB, 'mon film.mkv'), srv);

    assert.deepEqual(body, { kind: 'title', target_id: 't1', filename: 'mon film.mkv', size: 12 * MB, total_chunks: 3 });
});

test('envoie le jeton CSRF sur chaque requête', async () => {
    const srv = server();
    await run(fileOf(6 * MB), srv);

    assert.ok(srv.calls.length >= 4);
    for (const call of srv.calls) assert.equal(call.headers['X-CSRF-TOKEN'], 'jeton');
});

test('un fichier qui est un multiple exact de 5 Mo ne produit pas de morceau vide', async () => {
    const srv = server();
    await run(fileOf(10 * MB), srv);

    const sizes = srv.calls.filter((c) => c.url.startsWith('/chunk/')).map((c) => c.size);
    assert.deepEqual(sizes, [CHUNK, CHUNK]);
});

test('signale la progression, qui ne recule jamais et finit à 100 %', async () => {
    const seen = [];
    await run(fileOf(12 * MB), server(), { onProgress: (done, total) => seen.push([done, total]) });

    assert.deepEqual(seen.map(([d]) => d), [0, CHUNK, 2 * CHUNK, 12 * MB]);
    assert.ok(seen.every(([, total]) => total === 12 * MB));
});

test('réessaie un morceau en cas d\'erreur serveur ou de coupure réseau, puis réussit', async () => {
    const srv = server({ failChunk: { 1: { times: 2, status: 500 }, 2: { times: 1, network: true } } });
    const mediaId = await run(fileOf(12 * MB), srv);

    assert.equal(mediaId, 'm1');
    assert.equal(srv.attempts[0], 1);
    assert.equal(srv.attempts[1], 3);
    assert.equal(srv.attempts[2], 2);
});

test('attend de plus en plus longtemps entre deux tentatives', async () => {
    const waits = [];
    const srv = server({ failChunk: { 0: { times: 3, status: 503 } } });
    await run(fileOf(MB), srv, { sleep: async (ms) => waits.push(ms) });

    assert.deepEqual(waits, [500, 1000, 2000]);
});

test('abandonne après trop d\'échecs, avec un message qui dit quel morceau', async () => {
    const srv = server({ failChunk: { 1: { times: 99, status: 500 } } });

    await assert.rejects(run(fileOf(12 * MB), srv, { retries: 2 }), (error) => {
        assert.ok(error instanceof UploadError);
        assert.match(error.message, /morceau 2/);
        assert.equal(error.retryable, true);
        return true;
    });
    assert.equal(srv.attempts[1], 3);
    assert.equal(srv.calls.some((c) => c.url.startsWith('/complete/')), false);
});

test('ne réessaie pas une erreur du client, qui ne se règle pas en réessayant', async () => {
    const srv = server({ failChunk: { 0: { times: 99, status: 422, message: 'Morceau invalide' } } });

    await assert.rejects(run(fileOf(12 * MB), srv), /Morceau invalide/);
    assert.equal(srv.attempts[0], 1);
});

test('réessaie une limite de débit (429) et un jeton expiré (419)', async () => {
    for (const status of [429, 419]) {
        const srv = server({ failChunk: { 0: { times: 1, status } } });
        assert.equal(await run(fileOf(MB), srv), 'm1');
        assert.equal(srv.attempts[0], 2);
    }
});

test('affiche le refus du serveur au démarrage', async () => {
    await assert.rejects(run(fileOf(MB, 'a.exe'), server({ startStatus: 422 })), /Format non accepté/);
});

test('affiche le refus du serveur à l\'assemblage', async () => {
    await assert.rejects(run(fileOf(MB), server({ completeStatus: 422 })), /pas une vidéo valide/);
});

test('reprend un envoi interrompu : les morceaux déjà reçus ne sont pas renvoyés', async () => {
    const store = new Map([['homeflix-upload:title:t1:film.mp4:' + 12 * MB + ':1', 'u1']]);
    const memory = { get: (k) => store.get(k) ?? null, set: (k, v) => store.set(k, v), remove: (k) => store.delete(k) };
    const srv = server({ statusReply: json({ received: [0, 1], total_chunks: 3 }) });

    await run(fileOf(12 * MB), srv, { memory });

    const sent = srv.calls.filter((c) => c.url.startsWith('/chunk/')).map((c) => c.url);
    assert.deepEqual(sent, ['/chunk/u1/2']);
    assert.equal(srv.calls.some((c) => c.url === '/start'), false);
    assert.equal(store.size, 0);
});

test('reprend la progression là où elle en était', async () => {
    const store = new Map([['homeflix-upload:title:t1:film.mp4:' + 12 * MB + ':1', 'u1']]);
    const memory = { get: (k) => store.get(k) ?? null, set: (k, v) => store.set(k, v), remove: (k) => store.delete(k) };
    const seen = [];

    await run(fileOf(12 * MB), server({ statusReply: json({ received: [0, 1], total_chunks: 3 }) }), { memory, onProgress: (d) => seen.push(d) });

    assert.equal(seen[0], 2 * CHUNK);
    assert.equal(seen.at(-1), 12 * MB);
});

test('recommence si le serveur a oublié l\'envoi précédent', async () => {
    const store = new Map([['homeflix-upload:title:t1:film.mp4:' + 6 * MB + ':1', 'vieux']]);
    const memory = { get: (k) => store.get(k) ?? null, set: (k, v) => store.set(k, v), remove: (k) => store.delete(k) };
    const srv = server();

    await run(fileOf(6 * MB), srv, { memory });

    assert.ok(srv.calls.some((c) => c.url === '/start'));
    assert.equal(srv.calls.filter((c) => c.url.startsWith('/chunk/')).length, 2);
});

test('garde le numéro d\'envoi en mémoire pendant l\'envoi, et l\'oublie à la fin', async () => {
    const store = new Map();
    const memory = { get: (k) => store.get(k) ?? null, set: (k, v) => store.set(k, v), remove: (k) => store.delete(k) };
    let during = null;
    const srv = server();
    const inner = srv.fetchImpl;
    srv.fetchImpl = async (url, options) => { if (url.startsWith('/chunk/')) during = store.size; return inner(url, options); };

    await run(fileOf(6 * MB), srv, { memory });

    assert.equal(during, 1);
    assert.equal(store.size, 0);
});
