# 📊 SLiMS Graph Network Analysis

**Plugin untuk SLiMS** yang menampilkan berbagai analisis grafis dan visualisasi hubungan antar data bibliografi serta aktivitas pengguna perpustakaan.

## 🧑‍💻 Author  
Erwan Setyo Budi

---

## 🎯 Fitur Plugin

1. **Author Network**  
   Menampilkan visualisasi hubungan antar pengarang berdasarkan data bibliografi.  
   📍 `index.php?p=author_network`

2. **Gender Visitor Chart**  
   Menampilkan grafik jumlah pengunjung perpustakaan berdasarkan gender.  
   📍 `index.php?p=gender_visitor_chart`

3. **Loan Item Network**  
   Visualisasi hubungan antar pemustaka berdasarkan data peminjaman item yang sama.  
   📍 `index.php?p=loan_item_network`

4. **Publisher Topic Network**  
   Menampilkan keterkaitan antara penerbit dan topik buku yang diterbitkan.  
   📍 `index.php?p=publisher_topic_network`

5. **Title Year Trend**  
   Menampilkan tren penambahan buku berdasarkan waktu input (input_date).  
   📍 `index.php?p=title_year_trend`

6. **Topic Chart**  
   Visualisasi bubble chart untuk melihat kecenderungan topik dalam koleksi perpustakaan.  
   📍 `index.php?p=topic_chart`

7. **Topic Network**  
   Menampilkan jaringan keterkaitan antar topik berdasarkan kemunculan bersamaan dalam bibliografi.  
   📍 `index.php?p=topic_network`

---

## 📦 Cara Install

1. **Download** plugin `slims-graph` dari repo ini.
2. **Ekstrak** ke dalam folder:  
   ```
   slims-root/plugins/slims-graph
   ```
3. **Aktifkan plugin** dengan:
   - Login sebagai Super Admin
   - Masuk ke menu **Plugins**
   - Aktifkan plugin **slims-graph**

4. **Akses plugin** melalui OPAC dengan URL masing-masing (lihat bagian fitur).

---

## ⚙️ Fitur Umum Tiap Halaman

- 🔢 **Limit data** ditampilkan dengan parameter `limit` (default 100 baris)
- 🔍 **Filter topik** (pada fitur tertentu)
- 🔎 **Zoom In/Out**
- 📅 **Simpan sebagai JPG**
- 🔗 **Bagikan URL**
- 🖍️ **Legenda warna dan deskripsi interaktif**

---

## 📚 SLiMS Compatibility

- Direkomendasikan untuk digunakan pada SLiMS 9 atau versi terbaru.

---

## 🤝 Kontribusi

Pull request dan saran pengembangan sangat terbuka. Jangan ragu untuk fork dan modifikasi!

---

## 📢 Lisensi

Plugin ini dibagikan secara **bebas dan terbuka**. Silakan gunakan, modifikasi, dan sebarkan.

---

**Semoga bermanfaat.**  
— Erwan Setyo Budi

## Screen Shoot
![Screenshot 2025-03-23 at 07-32-53 Gender Visitor Chart](https://github.com/user-attachments/assets/c9bd560b-a275-4190-b397-92e9ad708732)
![Screenshot 2025-03-23 at 07-33-35 Loan Item Network](https://github.com/user-attachments/assets/f3e09de6-ed2d-4d38-b014-8c43600c644a)
![Screenshot 2025-03-23 at 07-34-02 Publisher Topic Network](https://github.com/user-attachments/assets/8ae4596e-2c67-41dd-a898-8a48c635383d)
![Screenshot 2025-03-23 at 07-34-32 Title Year Trend](https://github.com/user-attachments/assets/2546013f-9bbf-49e1-af72-3f51044cb5e3)
![Screenshot 2025-03-23 at 07-34-59 Topic Chart](https://github.com/user-attachments/assets/a1ff73eb-a71d-4980-a759-ff8a007fd360)
![Screenshot 2025-03-23 at 07-35-44 Topic Network](https://github.com/user-attachments/assets/082e69ab-74c0-4ada-a026-bb9acb71e620)
![Screenshot 2025-03-23 at 07-32-25 Author Network](https://github.com/user-attachments/assets/dcbd68ab-319b-4e44-9681-6ae1db0efdb3)
