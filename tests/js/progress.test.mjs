// Reprise et sauvegarde de la position, sans navigateur : `pnpm run test:js`.
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { mediaIdOf, trackProgress } from '../../resources/js/player-core.js';

const ID = '01a1eaab-f114-7ec0-be6f-343634818f76';
const URL = `/hls/${ID}/master.m3u8`;
const TEMPLATE = '/progression/__ID__';

function fakeVideo({ readyState = 0, duration = 600, currentTime = 0 } = {}) {
    const target = new EventTarget();
    return Object.assign(target, { readyState, duration, currentTime });
}

function fakeTimers() {
    const timers = { callbacks: new Map(), next: 1, cleared: [] };
    timers.set = (callback) => { timers.callbacks.set(timers.next, callback); return timers.next++; };
    timers.clear = (id) => { timers.cleared.push(id); timers.callbacks.delete(id); };
    timers.tick = () => [...timers.callbacks.values()].forEach((callback) => callback());
    return timers;
}

function fakeFetch(position = 0, { ok = true } = {}) {
    const calls = [];
    const impl = async (url, options = {}) => {
        calls.push({ url, ...options });
        if ((options.method ?? 'GET') === 'GET') {
            return { ok, json: async () => ({ position }) };
        }
        return { ok: true };
    };
    impl.calls = calls;
    return impl;
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

function start(video, fetchImpl, timers = fakeTimers()) {
    const tracker = trackProgress(video, URL, { template: TEMPLATE, csrf: 'tok', fetchImpl, timers, leaveTarget: new EventTarget() });
    return { tracker, timers };
}

const puts = (fetchImpl) => fetchImpl.calls.filter((call) => call.method === 'PUT');

test('lit l\'identifiant de la vidéo dans l\'URL du flux, et refuse le reste', () => {
    assert.equal(mediaIdOf(URL), ID);
    assert.equal(mediaIdOf('https://autre.site/hls/x/master.m3u8'), null);
    assert.equal(mediaIdOf(null), null);
});

test('ne fait rien sans URL de progression ou avec un flux invalide', () => {
    const fetchImpl = fakeFetch();
    trackProgress(fakeVideo(), URL, { template: '', csrf: 't', fetchImpl });
    trackProgress(fakeVideo(), '/autre', { template: TEMPLATE, csrf: 't', fetchImpl });
    assert.equal(fetchImpl.calls.length, 0);
});

test('reprend à la position connue une fois les métadonnées chargées', async () => {
    const video = fakeVideo({ readyState: 0 });
    const fetchImpl = fakeFetch(120);
    start(video, fetchImpl);
    await flush();
    assert.equal(video.currentTime, 0, 'rien tant que la vidéo n\'a pas de métadonnées');

    video.readyState = 1;
    video.dispatchEvent(new Event('loadedmetadata'));
    assert.equal(video.currentTime, 120);
    assert.equal(fetchImpl.calls[0].url, `/progression/${ID}`);
});

test('reprend aussi quand les métadonnées arrivent avant la réponse du serveur', async () => {
    const video = fakeVideo({ readyState: 1 });
    start(video, fakeFetch(75));
    await flush();
    assert.equal(video.currentTime, 75);
});

test('ne reprend pas dans les 5 dernières secondes ni à 0', async () => {
    const near = fakeVideo({ readyState: 1, duration: 100 });
    start(near, fakeFetch(98));
    const none = fakeVideo({ readyState: 1 });
    start(none, fakeFetch(0));
    await flush();
    assert.equal(near.currentTime, 0);
    assert.equal(none.currentTime, 0);
});

test('envoie la position toutes les 10 secondes pendant la lecture, avec le jeton CSRF', async () => {
    const video = fakeVideo({ readyState: 1 });
    const fetchImpl = fakeFetch(0);
    const { timers } = start(video, fetchImpl);
    await flush();

    video.currentTime = 42.5;
    video.dispatchEvent(new Event('play'));
    timers.tick();

    const [put] = puts(fetchImpl);
    assert.equal(put.url, `/progression/${ID}`);
    assert.equal(put.headers['X-CSRF-TOKEN'], 'tok');
    assert.equal(put.keepalive, true);
    assert.deepEqual(JSON.parse(put.body), { position: 42.5 });
});

test('n\'écrase jamais la position connue avant d\'avoir repris', async () => {
    const video = fakeVideo({ readyState: 0, currentTime: 3 });
    const fetchImpl = fakeFetch(200);
    const { timers } = start(video, fetchImpl);
    video.dispatchEvent(new Event('play'));
    timers.tick(); // la réponse du serveur n'est pas arrivée
    await flush();
    timers.tick(); // la reprise n'est pas appliquée : pas de métadonnées
    assert.equal(puts(fetchImpl).length, 0);
});

test('sauvegarde à la pause et à la fin, et arrête la minuterie', async () => {
    const video = fakeVideo({ readyState: 1 });
    const fetchImpl = fakeFetch(0);
    const { timers } = start(video, fetchImpl);
    await flush();
    video.dispatchEvent(new Event('play'));

    video.currentTime = 10;
    video.dispatchEvent(new Event('pause'));
    video.currentTime = 20;
    video.dispatchEvent(new Event('ended'));

    assert.equal(puts(fetchImpl).length, 2);
    assert.ok(timers.cleared.length >= 1);
    assert.equal(timers.callbacks.size, 0);
});

test('ne sauvegarde pas une position à 0', async () => {
    const video = fakeVideo({ readyState: 1, currentTime: 0 });
    const fetchImpl = fakeFetch(0);
    start(video, fetchImpl);
    await flush();
    video.dispatchEvent(new Event('pause'));
    assert.equal(puts(fetchImpl).length, 0);
});

test('sauvegarde quand la page se cache', async () => {
    const video = fakeVideo({ readyState: 1 });
    const fetchImpl = fakeFetch(0);
    const leaveTarget = Object.assign(new EventTarget(), { visibilityState: 'hidden' });
    trackProgress(video, URL, { template: TEMPLATE, csrf: 't', fetchImpl, timers: fakeTimers(), leaveTarget });
    await flush();
    video.currentTime = 33;
    leaveTarget.dispatchEvent(new Event('visibilitychange'));
    assert.equal(puts(fetchImpl).length, 1);

    leaveTarget.visibilityState = 'visible';
    leaveTarget.dispatchEvent(new Event('visibilitychange'));
    assert.equal(puts(fetchImpl).length, 1);
});

test('continue sans reprise quand le serveur refuse ou échoue, sans lever d\'erreur', async () => {
    const video = fakeVideo({ readyState: 1 });
    start(video, fakeFetch(50, { ok: false }));
    const failing = fakeVideo({ readyState: 1 });
    start(failing, async () => { throw new Error('réseau'); });
    await flush();
    assert.equal(video.currentTime, 0);
    assert.equal(failing.currentTime, 0);
});

test('une sauvegarde qui échoue ne casse pas la lecture', async () => {
    const video = fakeVideo({ readyState: 1 });
    const impl = async (url, options = {}) => {
        if ((options.method ?? 'GET') === 'GET') return { ok: true, json: async () => ({ position: 0 }) };
        throw new Error('réseau');
    };
    start(video, impl);
    await flush();
    video.currentTime = 5;
    video.dispatchEvent(new Event('pause'));
    await flush();
});

test('stop() détache tout : plus aucune sauvegarde', async () => {
    const video = fakeVideo({ readyState: 1 });
    const fetchImpl = fakeFetch(0);
    const { tracker } = start(video, fetchImpl);
    await flush();
    tracker.stop();
    video.currentTime = 9;
    video.dispatchEvent(new Event('pause'));
    assert.equal(puts(fetchImpl).length, 0);
});
