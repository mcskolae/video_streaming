// Tests du lecteur, sans navigateur : `pnpm run test:js`.
// hls.js est remplacé par un faux, la balise <video> aussi.
import assert from 'node:assert/strict';
import { test } from 'node:test';

// Le module importe hls.js ; en Node il se charge sans toucher au DOM tant qu'on ne l'instancie pas.
const { attach, isStreamUrl } = await import('../../resources/js/player.js');

const GOOD = '/hls/01a1eaab-f114-7ec0-be6f-343634818f76/master.m3u8';

function fakeVideo({ native = false } = {}) {
    return {
        src: '',
        attributes: { src: 'x' },
        loaded: 0,
        canPlayType: (type) => (native && type === 'application/vnd.apple.mpegurl' ? 'maybe' : ''),
        removeAttribute(name) { delete this.attributes[name]; this.src = ''; },
        load() { this.loaded++; },
    };
}

function fakeHls({ supported = true } = {}) {
    const instances = [];
    class FakeHls {
        static isSupported() { return supported; }
        static Events = { ERROR: 'error' };
        static ErrorTypes = { NETWORK_ERROR: 'networkError', MEDIA_ERROR: 'mediaError' };
        constructor() { this.handlers = {}; this.calls = []; instances.push(this); }
        on(event, handler) { this.handlers[event] = handler; }
        loadSource(url) { this.calls.push(['loadSource', url]); }
        attachMedia(video) { this.calls.push(['attachMedia', video]); }
        startLoad() { this.calls.push(['startLoad']); }
        recoverMediaError() { this.calls.push(['recoverMediaError']); }
        destroy() { this.calls.push(['destroy']); }
        fail(type, fatal = true) { this.handlers.error('error', { type, fatal }); }
    }
    return { FakeHls, instances };
}

test('n\'accepte que les flux HLS de ce site', () => {
    assert.equal(isStreamUrl(GOOD), true);

    for (const bad of [
        'https://evil.example/hls/01a1eaab-f114-7ec0-be6f-343634818f76/master.m3u8',
        '//evil.example/hls/01a1eaab-f114-7ec0-be6f-343634818f76/master.m3u8',
        'javascript:alert(1)',
        '/hls/../etc/passwd',
        '/hls/01a1eaab-f114-7ec0-be6f-343634818f76/../master.m3u8',
        '/other/01a1eaab-f114-7ec0-be6f-343634818f76/master.m3u8',
        '/hls/not-a-uuid/master.m3u8',
        '/hls/01a1eaab-f114-7ec0-be6f-343634818f76/master.m3u8?x=1',
        '/hls/01a1eaab-f114-7ec0-be6f-343634818f76/720p/index.m3u8',
        'data:text/html,<script>',
        '', null, undefined, 42,
    ]) {
        assert.equal(isStreamUrl(bad), false, `devrait refuser ${bad}`);
    }
});

test('refuse une source invalide sans rien charger', () => {
    const { FakeHls, instances } = fakeHls();
    const errors = [];

    const result = attach(fakeVideo(), 'https://evil.example/x.m3u8', { HlsImpl: FakeHls, onError: (m) => errors.push(m) });

    assert.equal(result, null);
    assert.deepEqual(errors, ['Source vidéo invalide.']);
    assert.equal(instances.length, 0);
});

test('lit nativement quand le navigateur sait (Safari), sans hls.js', () => {
    const { FakeHls, instances } = fakeHls();
    const video = fakeVideo({ native: true });

    const controller = attach(video, GOOD, { HlsImpl: FakeHls });

    assert.equal(video.src, GOOD);
    assert.equal(instances.length, 0);
    controller.destroy();
    assert.equal(video.src, '');
    assert.equal(video.loaded, 1);
});

test('utilise hls.js sinon : charge la source puis branche la vidéo', () => {
    const { FakeHls, instances } = fakeHls();
    const video = fakeVideo();

    attach(video, GOOD, { HlsImpl: FakeHls });

    assert.equal(instances.length, 1);
    assert.deepEqual(instances[0].calls, [['loadSource', GOOD], ['attachMedia', video]]);
});

test('dit clairement quand le navigateur ne sait pas lire', () => {
    const { FakeHls } = fakeHls({ supported: false });
    const errors = [];

    const result = attach(fakeVideo(), GOOD, { HlsImpl: FakeHls, onError: (m) => errors.push(m) });

    assert.equal(result, null);
    assert.match(errors[0], /ne sait pas lire/);
});

test('ignore une erreur qui n\'est pas fatale', () => {
    const { FakeHls, instances } = fakeHls();
    const errors = [];
    attach(fakeVideo(), GOOD, { HlsImpl: FakeHls, onError: (m) => errors.push(m) });

    instances[0].fail('networkError', false);

    assert.deepEqual(errors, []);
    assert.equal(instances[0].calls.some(([name]) => name === 'destroy'), false);
});

test('relance le chargement après une coupure réseau, trois fois au plus, puis abandonne', () => {
    const { FakeHls, instances } = fakeHls();
    const errors = [];
    attach(fakeVideo(), GOOD, { HlsImpl: FakeHls, onError: (m) => errors.push(m) });

    for (let i = 0; i < 4; i++) instances[0].fail('networkError');

    const names = instances[0].calls.map(([name]) => name);
    assert.equal(names.filter((n) => n === 'startLoad').length, 3);
    assert.equal(names.at(-1), 'destroy');
    assert.equal(errors.length, 1);
});

test('tente de récupérer une erreur de décodage, deux fois au plus, puis abandonne', () => {
    const { FakeHls, instances } = fakeHls();
    const errors = [];
    attach(fakeVideo(), GOOD, { HlsImpl: FakeHls, onError: (m) => errors.push(m) });

    for (let i = 0; i < 3; i++) instances[0].fail('mediaError');

    const names = instances[0].calls.map(([name]) => name);
    assert.equal(names.filter((n) => n === 'recoverMediaError').length, 2);
    assert.equal(names.at(-1), 'destroy');
    assert.match(errors[0], /La lecture a échoué/);
});

test('abandonne tout de suite sur une autre erreur fatale', () => {
    const { FakeHls, instances } = fakeHls();
    const errors = [];
    attach(fakeVideo(), GOOD, { HlsImpl: FakeHls, onError: (m) => errors.push(m) });

    instances[0].fail('otherError');

    assert.equal(instances[0].calls.at(-1)[0], 'destroy');
    assert.equal(errors.length, 1);
});

test('destroy libère hls.js', () => {
    const { FakeHls, instances } = fakeHls();

    attach(fakeVideo(), GOOD, { HlsImpl: FakeHls }).destroy();

    assert.equal(instances[0].calls.at(-1)[0], 'destroy');
});
