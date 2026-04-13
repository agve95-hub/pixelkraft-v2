Act as a Principal Software Architect, Site Reliability Engineer (SRE), Chief Information Security Officer (CISO), and Lead UI/UX Engineer. I am providing you with the complete codebase for my project, an artisanal "Git-to-Render" CMS called Pixelkraft, built on PHP/Laravel.

I do not just want a basic code review. I need an exhaustive, relentless audit designed to make this system absolutely bulletproof, highly available, and production-ready at an enterprise scale. It must not fail.

Execute the audit sequentially across the following strict domains. For every flaw you find, you MUST provide the exact code to fix it, the architectural pattern to replace it, or the infrastructure strategy to mitigate it. Do not skip a single line of these instructions.

### Phase 1: High Availability & System Resilience
1. Single Points of Failure: Analyze the Git-to-Render pipeline. What happens if the Git provider (GitHub/GitLab) goes down? What happens if a webhook drops? Provide a robust queueing and retry architecture to guarantee no data loss.
2. Concurrency & Race Conditions: Audit the repository parsing logic. What happens if two Git pushes happen within milliseconds? How is file locking or job atomic execution handled to prevent database or cache corruption?
3. Memory Management: Identify any loops, file-parsing routines, or Markdown/AST generators that could cause memory leaks or CPU spikes when processing massive Git repositories. 

### Phase 2: Advanced Caching & Edge Optimization
1. Cache Invalidation Strategies: The hardest part of a Git-to-Render CMS is knowing exactly what to clear. Audit the current caching strategy. Propose a hyper-efficient, tag-based caching architecture (e.g., using Redis) where only the updated Markdown files and their related indexes are purged, keeping the rest of the site served from memory.
2. Database Optimization: Identify missing indexes, N+1 query problems, and heavy database bottlenecks. Rewrite the offending Eloquent queries or propose raw SQL where Eloquent is too slow.

### Phase 3: Hardcore Security & Penetration Testing
1. Webhook Payload Verification: Audit how Git payloads are received. Write the code to ensure strict cryptographic verification of webhook signatures to prevent spoofing.
2. SSRF & Path Traversal: Since this parses external Git files, do a deep dive for Server-Side Request Forgery (SSRF) vulnerabilities. Is it possible for a malicious Markdown file to trigger unauthorized internal server requests or read files outside the intended directory?
3. Dependency Audit: Identify any known vulnerabilities in the approach used for the current Laravel configuration and PHP setup.

### Phase 4: Code Quality & Framework Mastery
1. SOLID Principles & Decoupling: Where is the code too tightly coupled? Identify controllers or models that are doing too much work. Extract this logic into Services, Actions, or Repositories and provide the refactored code.
2. Strict Typing & Error Handling: Find areas lacking strict PHP typing (`declare(strict_types=1);`). Audit the Exception Handling—are errors failing silently? Provide a strategy for global exception catching that integrates with tools like Sentry or Datadog without exposing stack traces to the user.

### Phase 5: UI/UX "Vibe" & Frontend Resilience
1. Visual Consistency: Audit the frontend assets and Blade templates. Ensure the UI strictly adheres to a "vibe coding" aesthetic: minimalist Bento grid layouts, seamless dark themes, and perfect typography scaling using the Inter font. Point out any CSS/layout inconsistencies.
2. Graceful Degradation & Error States: What does the user see when a Git pull fails or a markdown file is malformed? Design and suggest robust, beautiful error states (404, 500, parsing errors) that guide the user rather than abandoning them.
3. Asynchronous UX: Where can we replace synchronous page loads with optimistic UI updates or Livewire/Inertia components to make the dashboard feel instantly responsive?

### Phase 6: Technology Stack Brutality & Open-Source Leverage
1. Language & Framework Viability: Is PHP/Laravel actually the optimal tool for the heavy-lifting parts of a Git-to-Render engine? Identify specific bottlenecks (e.g., AST parsing, Git tree walking) that should be ripped out and offloaded to microservices written in Go or Rust.
2. The "Drop or Keep" Matrix: Ruthlessly audit every dependency in `composer.json` and `package.json`. Tell me exactly which packages are bloat, which are security risks, and which must be kept. 
3. Open-Source Replacements: Stop me from reinventing the wheel. Identify any custom logic in this codebase that should be deleted and replaced with battle-tested open-source packages (e.g., swapping a custom search engine for Meilisearch/Typesense, or using a specific League package for Markdown instead of a custom parser).

### Phase 7: Telemetry, Observability & "Path to Perfect" Production Roadmap
1. SRE Observability: A system isn't production-ready if it's flying blind. Outline exactly how to implement OpenTelemetry, Prometheus, or Datadog to trace the exact millisecond latency of a Git webhook turning into a rendered page.
2. CI/CD Pipeline: Outline the exact GitHub Actions or GitLab CI yaml structure needed to test, statically analyze (PHPStan/Pint), and deploy this codebase with zero downtime.
3. Infrastructure Recommendations: Based on the code, what is the ideal hosting environment for maximum robustness? (e.g., Serverless via Laravel Vapor, Kubernetes, or balanced VPS clusters). 
4. Final Verdict: What is the single biggest threat to this application crashing in production right now?

Below is the repository structure and the file contents:

[INSERT REPOSITORY TREE HERE]

[INSERT ALL FILE CONTENTS HERE]
