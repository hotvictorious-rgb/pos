/**
 * ARCHIVED / RETIRED LEGACY SPA ENTRYPOINT:
 * Production interface is 100% Laravel Blade (resources/views/).
 * This file is retained exclusively for CI build and typecheck verification.
 * See resources/js/ARCHIVED_LEGACY_SPA_NOTICE.md
 */

import {StrictMode} from 'react';
import {createRoot} from 'react-dom/client';
import App from './App.tsx';
import './index.css';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
