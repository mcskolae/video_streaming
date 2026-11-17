// Logique du lecteur vidéo HLS, sans dépendance : testable avec Node seul.
// Le fichier player.js lui fournit hls.js (partout, sauf lecture native sur Safari et iOS).
//
// Le flux adapte sa qualité à la connexion (1080p, 720p…) et permet de se déplacer dans la vidéo.
// Seule une URL de la forme /hls/<id>/master.m3u8 est acceptée : le lecteur ne charge jamais de source externe.

const STREAM_URL = /^\/hls\/[0-9a-f-]{36}\/master\.m3u8$/;

export function isStreamUrl(url) {
    return typeof url === 'string' && STREAM_URL.test(url);
}

/** Identifiant de la vidéo d'après son URL de flux, ou null si l'URL n'est pas un flux valide. */
export function mediaIdOf(url) {
    return isStreamUrl(url) ? url.split('/')[2] : null;
}

/**
 * Reprise et sauvegarde de la position, pour un membre connecté.
 *
 * - À la reprise, le lecteur se place à la dernière position connue (le serveur répond 0 si la vidéo a été vue en entier).
 * - Pendant la lecture, la position est envoyée toutes les `intervalMs`, puis à chaque pause, à la fin et quand la page se cache.
 * - Tant que la reprise n'est pas appliquée, rien n'est envoyé : on n'écrase pas la position connue par 0.
 *
 * @param {object} options
 * @param {string} options.template URL avec le jeton __ID__
 * @param {string} options.csrf
 * @returns {{stop: () => void}}
 */
export function trackProgress(video, url, {
    template,
    csrf,
    fetchImpl = (...args) => fetch(...args),
    intervalMs = 10000,
    leaveTarget = typeof document === 'undefined' ? null : document,
    timers = { set: (...args) => setInterval(...args), clear: (id) => clearInterval(id) },
} = {}) {
    const id = mediaIdOf(url);

    if (id === null || !template) {
        return { stop() {} };
    }

    const endpoint = template.replace('__ID__', id);
    const headers = { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' };
    let resumeAt = 0;
    let ready = false; // la réponse du serveur est arrivée
    let applied = false; // la position de reprise est appliquée (ou inutile)
    let stopped = false;
    let timer = null;

    const applyResume = () => {
        if (!ready || applied || video.readyState < 1) {
            return;
        }

        applied = true;

        // Pas de reprise dans les dernières secondes : la lecture repartirait de la fin.
        if (resumeAt > 0 && (!Number.isFinite(video.duration) || resumeAt < video.duration - 5)) {
            video.currentTime = resumeAt;
        }
    };

    const save = () => {
        if (stopped || !applied || !(video.currentTime > 0)) {
            return;
        }

        fetchImpl(endpoint, {
            method: 'PUT',
            headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify({ position: video.currentTime }),
            keepalive: true,
        }).catch(() => {
            // Une sauvegarde manquée n'interrompt pas la lecture : la suivante la remplace.
        });
    };

    const onPlay = () => {
        timer ??= timers.set(save, intervalMs);
    };
    const onPause = () => {
        timers.clear(timer);
        timer = null;
        save();
    };
    const onHidden = () => {
        if (leaveTarget?.visibilityState === 'hidden') {
            save();
        }
    };

    video.addEventListener('loadedmetadata', applyResume);
    video.addEventListener('play', onPlay);
    video.addEventListener('pause', onPause);
    video.addEventListener('ended', onPause);
    leaveTarget?.addEventListener('visibilitychange', onHidden);

    fetchImpl(endpoint, { headers })
        .then((response) => (response.ok ? response.json() : { position: 0 }))
        .then((body) => {
            resumeAt = Number.isFinite(body?.position) ? body.position : 0;
        })
        .catch(() => {
            resumeAt = 0;
        })
        .finally(() => {
            ready = true;
            applyResume();
        });

    return {
        stop() {
            stopped = true;
            timers.clear(timer);
            video.removeEventListener('loadedmetadata', applyResume);
            video.removeEventListener('play', onPlay);
            video.removeEventListener('pause', onPause);
            video.removeEventListener('ended', onPause);
            leaveTarget?.removeEventListener('visibilitychange', onHidden);
        },
    };
}

/**
 * Branche un flux sur une balise <video>.
 *
 * @returns {{destroy: () => void}|null} null si le flux n'a pas pu être branché (le message est alors passé à onError)
 */
export function attach(video, url, { HlsImpl, onError = () => {} } = {}) {
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
export function setUp(root, HlsImpl) {
    const video = root.querySelector('video[data-player-video]');

    if (!video) {
        return;
    }

    const progressTemplate = root.querySelector('[data-progress-url]')?.dataset.progressUrl ?? '';
    const csrf = root.querySelector?.('meta[name=csrf-token]')?.getAttribute('content') ?? '';
    let tracker = null;

    const track = (url) => {
        tracker?.stop();
        tracker = progressTemplate ? trackProgress(video, url, { template: progressTemplate, csrf }) : null;
    };

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
        tracker?.stop();
        show('');
        current = attach(video, url, { HlsImpl, onError: show });

        if (current === null) {
            return;
        }

        track(url);

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
        current = attach(video, movieUrl, { HlsImpl, onError: show });

        if (current !== null) {
            track(movieUrl);
        }
    }

    for (const button of root.querySelectorAll('[data-play-episode]')) {
        button.addEventListener('click', () => {
            play(button.dataset.hls, button.dataset.label);
            video.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
        });
    }

    // « Reprendre la lecture » arrive avec ?lire=<vidéo> : on charge cet épisode, la position est reprise toute seule.
    const wanted = new URLSearchParams(typeof location === 'undefined' ? '' : location.search).get('lire');

    if (wanted && /^[0-9a-f-]{36}$/.test(wanted)) {
        const target = [...root.querySelectorAll('[data-play-episode]')].find((button) => button.dataset.hls === `/hls/${wanted}/master.m3u8`);

        target?.closest('details')?.setAttribute('open', '');
        target?.click();
    }
}
