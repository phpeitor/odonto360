import { Alignment, Fit, Layout, Rive, RuntimeLoader } from '@rive-app/canvas';
import wasmUrl from '@rive-app/canvas/rive.wasm?url';

RuntimeLoader.setWasmUrl(wasmUrl);

// Este .riv no usa data binding (view model), así que los inputs de la state machine son la única vía.
Rive.suppressDeprecationWarnings = ['state-machine-inputs'];

const STATE_MACHINE = 'State Machine 1';
const LOOK_PER_CHARACTER = 4;
const SUCCESS_REDIRECT_DELAY_MS = 1500;

const EMAIL = '[wire\\:model="data.email"]';
const PASSWORD = '[wire\\:model="data.password"]';

/** Inputs de la state machine: Check (bool), Look (0-100), hands_up (bool), success y fail (triggers). */
let inputs = {};
let loaded = false;
let redirecting = false;

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const set = (name, value) => {
    if (inputs[name]) {
        inputs[name].value = value;
    }
};

const fire = (name) => inputs[name]?.fire();

const look = (field) => set('Look', Math.min(100, field.value.length * LOOK_PER_CHARACTER));

function init() {
    const canvas = document.querySelector('[data-tooth-canvas]');

    if (!canvas || canvas.dataset.ready) {
        return;
    }

    canvas.dataset.ready = '1';

    const rive = new Rive({
        src: canvas.dataset.src,
        canvas,
        stateMachine: STATE_MACHINE,
        autoplay: true,
        layout: new Layout({ fit: Fit.Contain, alignment: Alignment.Center }),
        onLoad: () => {
            rive.resizeDrawingSurfaceToCanvas();
            inputs = Object.fromEntries(rive.stateMachineInputs(STATE_MACHINE).map((input) => [input.name, input]));
            loaded = true;
        },
        onLoadError: () => canvas.closest('[data-tooth-login]')?.remove(),
    });

    window.addEventListener('resize', () => loaded && rive.resizeDrawingSurfaceToCanvas());
}

document.addEventListener('focusin', (event) => {
    if (!loaded || reduceMotion) {
        return;
    }

    if (event.target.matches?.(EMAIL)) {
        set('hands_up', false);
        set('Check', true);
        look(event.target);
    } else if (event.target.matches?.(PASSWORD)) {
        set('Check', false);
        // Con la contraseña visible el diente puede mirar.
        set('hands_up', event.target.type === 'password');
    }
});

document.addEventListener('focusout', (event) => {
    if (event.target.matches?.(EMAIL)) {
        set('Check', false);
    } else if (event.target.matches?.(PASSWORD)) {
        set('hands_up', false);
    }
});

document.addEventListener('input', (event) => {
    if (loaded && !reduceMotion && event.target.matches?.(EMAIL)) {
        look(event.target);
    }
});

window.addEventListener('login-failed', () => {
    set('Check', false);
    set('hands_up', false);
    fire('fail');
});

window.addEventListener('login-succeeded', (event) => {
    if (redirecting) {
        return;
    }

    redirecting = true;

    const target = new URL(event.detail?.url ?? '/', window.location.href);
    const url = target.origin === window.location.origin ? target.href : '/';

    set('Check', false);
    set('hands_up', false);
    fire('success');

    window.setTimeout(() => window.location.assign(url), loaded && !reduceMotion ? SUCCESS_REDIRECT_DELAY_MS : 0);
});

init();
document.addEventListener('livewire:navigated', init);
