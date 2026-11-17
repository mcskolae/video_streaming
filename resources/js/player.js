// Lecteur vidéo HLS : hls.js partout, lecture native sur Safari et iOS.
//
// Le flux adapte sa qualité à la connexion (1080p, 720p…) et permet de se déplacer dans la vidéo.
// Seule une URL de la forme /hls/<id>/master.m3u8 est acceptée : le lecteur ne charge jamais de source externe.

import Hls from 'hls.js';

const STREAM_URL = /^\/hls\/[0-9a-f-]{36}\/master\.m3u8$/;

export function isStreamUrl(url) {
    return typeof url === 'string' && STREAM_URL.test(url);
}

/**
 * Branche un flux sur une balise <video>.
 *
 * @returns {{destroy: () => void}|null} null si le flux n'a pas pu être branché (le message est alors passé à onError)
 */
export function attach(video, url, { HlsImpl = Hls, onError = () => {} } = {}) {
    if (!isStreamUrl(url)) {
        onError('Source vidéo invalide.');

        return null;
    }

    // Safari et iOS lisent HLS sans bibliothèque.
    if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = url;

        return {
            destroy() {
                video.removeAttribute('src');
                video.load();
            },
        };
    }

    if (!HlsImpl.isSupported()) {
        onError('Votre navigateur ne sait pas lire ce format vidéo.');

        return null;
    }

    const hls = new HlsImpl();
    let networkRetries = 0;
    let mediaRecoveries = 0;

    hls.on(HlsImpl.Events.ERROR, (_event, data) => {
        // Une erreur non fatale est absorbée par hls.js (il change de qualité, il réessaie).
        if (!data.fatal) {
            return;
        }

        if (data.type === HlsImpl.ErrorTypes.NETWORK_ERROR && networkRetries < 3) {
            networkRetries++;
            hls.startLoad();

            return;
        }

        if (data.type === HlsImpl.ErrorTypes.MEDIA_ERROR && mediaRecoveries < 2) {
            mediaRecoveries++;
            hls.recoverMediaError();

            return;
        }

        hls.destroy();
        onError('La lecture a échoué. Réessayez dans un instant.');
    });

    hls.loadSource(url);
    hls.attachMedia(video);

    return { destroy: () => hls.destroy() };
}

/** Branche la page : un film (flux déjà choisi) ou une série (boutons « Lire » par épisode). */
export function setUp(root) {
    const video = root.querySelector('video[data-player-video]');

    if (!video) {
        return;
    }

    const message = root.querySelector('[data-player-message]');
    const caption = root.querySelector('[data-player-caption]');
    let current = null;

    const show = (text) => {
        if (message) {
            message.textContent = text;
            message.hidden = text === '';
        }
    };

    const play = (url, label) => {
        current?.destroy();
        show('');
        current = attach(video, url, { onError: show });

        if (current === null) {
            return;
        }

        video.hidden = false;

        if (caption) {
            caption.textContent = label ? `En lecture : ${label}` : '';
        }

        video.play?.().catch(() => {
            // La lecture automatique peut être refusée par le navigateur : l'utilisateur appuie sur lecture.
        });
    };

    const movieUrl = video.dataset.hls;

    if (movieUrl) {
        // Un film : le flux est branché tout de suite, mais ne se charge qu'à l'appui sur lecture (preload="none").
        current = attach(video, movieUrl, { onError: show });
    }

    for (const button of root.querySelectorAll('[data-play-episode]')) {
        button.addEventListener('click', () => {
            play(button.dataset.hls, button.dataset.label);
            video.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
        });
    }
}

if (typeof document !== 'undefined') {
    setUp(document);
}
