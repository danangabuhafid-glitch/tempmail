/**
 * Cloudflare Email Worker — meneruskan email masuk ke webhook aplikasi TempMail.
 *
 * Cara pasang (lihat SETUP.md untuk langkah lengkap):
 * 1. Cloudflare Dashboard → Workers & Pages → Create Worker → tempel kode ini.
 * 2. Settings worker → Variables and Secrets:
 *      WEBHOOK_URL   = https://tempmail.domainmu.com/inbound/email
 *      WEBHOOK_TOKEN = (samakan dengan INBOUND_WEBHOOK_TOKEN di .env aplikasi)
 * 3. Email → Email Routing → Routing rules → Catch-all → Action: Send to Worker.
 *    Ulangi untuk kedua domain.
 */

// Samakan dengan TEMPMAIL_MAX_EMAIL_BYTES di aplikasi (default 15 MB)
const MAX_BYTES = 15 * 1024 * 1024;

export default {
  async email(message, env, ctx) {
    // Email kebesaran pasti ditolak aplikasi (413) — tolak final di sini,
    // jangan biarkan MTA pengirim retry tanpa akhir.
    if (message.rawSize > MAX_BYTES) {
      message.setReject("Message too large");
      return;
    }

    const raw = await new Response(message.raw).arrayBuffer();

    const res = await fetch(env.WEBHOOK_URL, {
      method: "POST",
      headers: {
        "Content-Type": "message/rfc822",
        "X-Webhook-Token": env.WEBHOOK_TOKEN,
        "X-Envelope-To": message.to,
        "X-Envelope-From": message.from,
      },
      body: raw,
    });

    if (res.ok) return;

    // 4xx (kecuali 429) = kegagalan PERMANEN — token salah, domain tak dilayani,
    // kebesaran. Retry tidak akan menolong; bounce dengan jelas supaya kelihatan.
    if (res.status >= 400 && res.status < 500 && res.status !== 429) {
      message.setReject(`Rejected by webhook: HTTP ${res.status}`);
      return;
    }

    // 5xx / 429 = kegagalan SEMENTARA — lempar error supaya server pengirim
    // mencoba mengirim ulang nanti (email tidak hilang saat aplikasi down).
    throw new Error(`Webhook gagal: HTTP ${res.status}`);
  },
};
