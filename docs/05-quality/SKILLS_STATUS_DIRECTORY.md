# Direktori Komprehensif Status 635 Skill Antigravity IDE

**Tanggal:** 2026-10-10  
**Format:** Matriks 1-baris per identitas skill dengan status lengkap.  
**Data Lengkap (JSON):** [SKILLS_COMPREHENSIVE_MATRIX.json](./SKILLS_COMPREHENSIVE_MATRIX.json)

---

## 1. Ringkasan Status Keseluruhan

| Status Metrik | Jumlah | Keterangan |
|---|---|---|
| **Total Target Terdaftar** | **635** | Di `~/.gemini/config/skills/` |
| **INSTALLED (Utuh)** | **634** | 99.8% paket fisik lengkap |
| **INSTALLED_PARTIAL** | **1** | `gstack` (symlink /private/tmp/ terhapus) |
| **DISCOVERED_ACTIVE** | **202** | Deskripsi lengkap termuat di context prompt |
| **DISCOVERED_NAME_ONLY** | **433** | Terdaftar di katalog IDE, termuat on-demand saat dipanggil |
| **NATIVE_COMPATIBLE** | **502** | Berjalan langsung tanpa translasi harness |
| **ADAPTED** | **113** | Dipetakan dari Claude Code / Codex ke tool Antigravity |
| **DEPRECATED** | **20** | Ditandai usang oleh upstream |
| **TESTED** | **14** | Lolos pengujian skenario sandbox terisolasi |
| **BLOCKED** | **36** | Membutuhkan runtime Docker / server MCP eksternal |

---

## 2. 9 Skill Utama yang Dioptimalkan ke Workspace Scope (.agents/skills)

| ID Skill | Scope | Status Discovery | Domain |
|---|---|---|---|
| `anti-slop-design` | **Workspace (Top Priority)** | **DISCOVERED_ACTIVE** | E-Commerce / UX / Laravel |
| `laravel-development-workflow` | **Workspace (Top Priority)** | **DISCOVERED_ACTIVE** | E-Commerce / UX / Laravel |
| `laravel-expert` | **Workspace (Top Priority)** | **DISCOVERED_ACTIVE** | E-Commerce / UX / Laravel |
| `laravel-security-audit` | **Workspace (Top Priority)** | **DISCOVERED_ACTIVE** | E-Commerce / UX / Laravel |
| `stitch-loop` | **Workspace (Top Priority)** | **DISCOVERED_ACTIVE** | E-Commerce / UX / Laravel |
| `stitch-ui-design` | **Workspace (Top Priority)** | **DISCOVERED_ACTIVE** | E-Commerce / UX / Laravel |
| `ui-ux-designer` | **Workspace (Top Priority)** | **DISCOVERED_ACTIVE** | E-Commerce / UX / Laravel |
| `ux-audit` | **Workspace (Top Priority)** | **DISCOVERED_ACTIVE** | E-Commerce / UX / Laravel |
| `wcag-audit-patterns` | **Workspace (Top Priority)** | **DISCOVERED_ACTIVE** | E-Commerce / UX / Laravel |

---

## 3. Sampel Matriks Koleksi Skill (50 Sampel Pertama)

| ID Skill | Checksum | Status Instalasi | Status Discovery | Adaptasi | Status Uji |
|---|---|---|---|---|---|
| `accessibility` | `2762f21d09d1` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `agency-docs-updater` | `9867c844bd40` | INSTALLED | DISCOVERED_ACTIVE | NATIVE_COMPATIBLE | NOT_TESTED |
| `agency-meetup-publish` | `ebdde25fb4fe` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `agency-socials` | `8f6f438447f2` | INSTALLED | DISCOVERED_ACTIVE | NATIVE_COMPATIBLE | NOT_TESTED |
| `agent-architecture-audit` | `64f57e232c35` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | ADAPTED (Claude Code mapping) | NOT_TESTED |
| `agent-cli` | `eb05624f1e4f` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | ADAPTED (Claude Code mapping) | NOT_TESTED |
| `agent-eval` | `57fa683e356b` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | ADAPTED (Claude Code mapping) | NOT_TESTED |
| `agent-harness-construction` | `e7fb390a6663` | INSTALLED | DISCOVERED_ACTIVE | ADAPTED (Claude Code mapping) | NOT_TESTED |
| `agent-introspection-debugging` | `0cdbfe3c822f` | INSTALLED | DISCOVERED_ACTIVE | ADAPTED (Claude Code mapping) | NOT_TESTED |
| `agent-payment-x402` | `78ac50508665` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | DEPRECATED (historical reference only) | NOT_TESTED |
| `agent-self-evaluation` | `96bb21eb3776` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | ADAPTED (Claude Code mapping) | NOT_TESTED |
| `agent-sort` | `5847fc20cf0f` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | ADAPTED (Claude Code mapping) | NOT_TESTED |
| `agentic-engineering` | `41c32c20ec97` | INSTALLED | DISCOVERED_ACTIVE | NATIVE_COMPATIBLE | NOT_TESTED |
| `agentic-os` | `e1d120735365` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | DEPRECATED (historical reference only) | NOT_TESTED |
| `ai-first-engineering` | `114913bdcb61` | INSTALLED | DISCOVERED_ACTIVE | NATIVE_COMPATIBLE | NOT_TESTED |
| `ai-regression-testing` | `83f2f5c4083a` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `android-clean-architecture` | `20cbb9d77784` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `angular-developer` | `28ee37a8cf63` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `animate` | `b8ede1612704` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `animate-expo` | `9a01a91701e6` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | DEPRECATED (historical reference only) | NOT_TESTED |
| `animation-vocabulary` | `40d2b27adfdf` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `annotate` | `66b985478e32` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `anon` | `840e9f4302a8` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `anti-slop-design` | `c32fd139d80c` | INSTALLED | DISCOVERED_ACTIVE | ADAPTED (Claude Code mapping) | TESTED (PASS in Sandbox) |
| `api-and-interface-design` | `e83d34f52ff1` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `api-connector-builder` | `00a0d01e234f` | INSTALLED | DISCOVERED_ACTIVE | NATIVE_COMPATIBLE | NOT_TESTED |
| `api-design` | `250e464413a1` | INSTALLED | DISCOVERED_ACTIVE | NATIVE_COMPATIBLE | NOT_TESTED |
| `app-release` | `b810c457ebe9` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `apple-design` | `77bb63b7043b` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `archify` | `cf348309c535` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `architecture-decision-records` | `263b20e81801` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | DEPRECATED (historical reference only) | NOT_TESTED |
| `article-writing` | `4aa61709450a` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `artifacts-builder` | `8455a64e627d` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `ask-matt` | `1371f5ce005d` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `ask-sonner` | `39f3ee185b3a` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `audit` | `391764927e11` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `automation-advisor` | `934789cf524a` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `automation-audit-ops` | `71aa878fd06e` | INSTALLED | DISCOVERED_ACTIVE | NATIVE_COMPATIBLE | NOT_TESTED |
| `autonomous-agent-harness` | `74afde3fa6e8` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | ADAPTED (Claude Code mapping) | NOT_TESTED |
| `autonomous-loops` | `c2db79729550` | INSTALLED | DISCOVERED_ACTIVE | ADAPTED (Claude Code mapping) | NOT_TESTED |
| `backend-patterns` | `9b983d0297a9` | INSTALLED | DISCOVERED_ACTIVE | NATIVE_COMPATIBLE | NOT_TESTED |
| `balanced` | `2c3a0b53af9d` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `banner-design` | `913d9c4b2a3b` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `benchmark` | `58e34c3ccf60` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `benchmark-methodology` | `f24585262b9c` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `benchmark-optimization-loop` | `f11208b6a86b` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | ADAPTED (Claude Code mapping) | NOT_TESTED |
| `blender-motion-state-inspection` | `97b8aee8822a` | INSTALLED | DISCOVERED_ACTIVE | ADAPTED (Claude Code mapping) | NOT_TESTED |
| `blueprint` | `878eae8ed0ea` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |
| `brand` | `6a450ee1a83a` | INSTALLED | DISCOVERED_ACTIVE | NATIVE_COMPATIBLE | NOT_TESTED |
| `brand-agency` | `4ad80735b9b4` | INSTALLED | DISCOVERED_NAME_ONLY (budget limit) | NATIVE_COMPATIBLE | NOT_TESTED |

*(Daftar lengkap seluruh 635 baris tersedia dalam format JSON di [SKILLS_COMPREHENSIVE_MATRIX.json](./SKILLS_COMPREHENSIVE_MATRIX.json))*
