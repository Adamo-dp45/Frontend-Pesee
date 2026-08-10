import { startStimulusApp } from '@symfony/stimulus-bridge';
import { registerReactControllerComponents } from '@symfony/ux-react';

/*
    - Les composants React sont enregistrés AVANT le démarrage de Stimulus : le contrôleur
      'symfony--ux-react--react' résout le nom du composant dès qu'il se connecte, et il se
      connecte au tout premier balayage du document. Enregistrer après revient à lui présenter
      une liste vide, et le point de montage reste blanc.
*/
registerReactControllerComponents(require.context('./react/controllers', true, /\.(j|t)sx?$/));

// Registers Stimulus controllers from controllers.json and in the controllers/ directory
export const app = startStimulusApp(import.meta.webpackContext('@symfony/stimulus-bridge/lazy-controller-loader!./controllers', {
    recursive: true,
    regExp: /\.[jt]sx?$/,
}));
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
