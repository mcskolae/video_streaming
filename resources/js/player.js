// Lecteur vidéo HLS : hls.js partout, lecture native sur Safari et iOS.
// La logique est dans player-core.js (testée sans navigateur) ; ce fichier lui fournit hls.js et démarre la page.

import Hls from 'hls.js';
import { setUp } from './player-core.js';

setUp(document, Hls);
