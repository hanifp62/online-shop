# ATM Analysis Template (Asumsi → Tes → Metrik)

Satu baris per asumsi berisiko. Urut dari paling load-bearing.

| # | Asumsi (hipotesis yg bisa gagal) | Tes termurah | Metrik + ambang lanjut/batal | Status |
|---|---|---|---|---|
| 1 | _ | _ | Lanjut jika _ / Batal jika _ | ☐ |
| 2 | _ | _ | Lanjut jika _ / Batal jika _ | ☐ |

Aturan:
- Asumsi tanpa tes = utang yang harus dicatat di Decision Log.
- Tes termurah dulu: baca kode/log > tanya user/data > prototipe > build penuh.
- Ambang harus angka + jendela waktu ("konversi checkout ≥2% dalam 2 minggu").
