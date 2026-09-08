# ARCHITECTURAL NOTICE: RETIRED LEGACY REACT SPA

> **CRITICAL ARCHITECTURAL DIRECTIVE FOR DEVELOPERS & AI AGENTS:**
> 
> The TypeScript/React single-page application located in `resources/js/` is **DECOMMISSIONED AND PERMANENTLY RETIRED**.
> 
> 1. **Production Architecture**:
>    - Production user interface is **100% server-rendered Laravel Blade** (`resources/views/`).
>    - Production data flow is **Browser → Blade Template → Laravel Controller → Application Services → MySQL Database**.
>    - All business rules, authentication, permissions, tenant scoping, inventory math, and financial transactions are strictly enforced on the server.
> 
> 2. **Purpose of Retaining `resources/js/`**:
>    - This directory is retained **strictly** to maintain CI pipeline compatibility for `npm run lint` (`tsc --noEmit`) and `npm run build` (`vite build`).
>    - It is an archived legacy client shell.
> 
> 3. **Prohibited Actions**:
>    - **DO NOT** add new production features to React components.
>    - **DO NOT** rely on client-side localStorage, shadow ledgers, or offline-sync mechanisms in `resources/js/lib/storage.ts`.
>    - **DO NOT** assume browser client state is authoritative.
> 
> Any future frontend modifications or features MUST be implemented in the authoritative Laravel Blade templates under `resources/views/`.
