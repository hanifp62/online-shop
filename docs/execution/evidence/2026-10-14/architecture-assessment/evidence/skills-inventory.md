# Inventaris skills dan MCP (read-only)

| Keluarga | Asal terverifikasi | Versi | Alasan penggunaan audit |
|---|---|---|---|
| Superpowers | cache npm OpenCode `node_modules/superpowers` | package 6.4.2 | workflow verifikasi/brainstorming; tidak menjalankan skill sebagai audit browser |
| Impeccable | `.agents/skills/impeccable/SKILL.md`; `.claude/skills/impeccable/SKILL.md` | metadata 4.5.2 | lensa evaluasi UI; bukan pengganti runtime evidence |
| Matt Pocock TDD | `~/.agents/skills/tdd/SKILL.md`, metadata author dan MIT | tidak tercantum | referensi test-first; suite tidak dieksekusi |
| ECC | `~/.config/opencode/skills/configure-ecc/SKILL.md` dan koleksi lokal OpenCode | release tidak terverifikasi | prosedur evidence-first dan gate; tidak mengubah instalasi |
| UI UX Pro Max | `.agents/skills/ui-ux-pro-max/SKILL.md` | tidak tercantum | kerangka UX; bukan audit WCAG yang dijalankan |
| Taste | `skills-lock.json` entri `design-taste-frontend`, `brandkit`, `gpt-taste`; source `Leonxlnx/taste-skill` | lock hash per skill; release tidak tercantum | arahan visual; tidak dipakai mengubah produk |
| Animated/UI references | `skills-lock.json` sumber `emilkowalski/skills` | hashes lock; release tidak tercantum | opsional saat remediasi motion |

MCP resource discovery pada snapshot menghasilkan `resources=[]`, `templates=[]`; tidak ada sumber MCP yang dipakai. Inventaris ini tidak menyatakan seluruh skill dipakai aktif atau versi yang tidak tersedia. Tidak ada pengubahan skill.
