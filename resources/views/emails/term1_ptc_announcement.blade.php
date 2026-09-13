<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <title>AMIS Announcement – Term 1 Special Awards Recognition & PTC</title>
  <style>
    body, table, td, p, a, li, blockquote {
      -webkit-text-size-adjust: 100%;
      -ms-text-size-adjust: 100%;
    }
    table, td {
      mso-table-lspace: 0pt;
      mso-table-rspace: 0pt;
    }
    img {
      -ms-interpolation-mode: bicubic;
      border: 0;
      height: auto;
      line-height: 100%;
      outline: none;
      text-decoration: none;
      display: block;
      max-width: 100%;
    }
    body {
      height: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
      width: 100% !important;
      background-color: #f1f5f9;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      color: #1e293b;
      -webkit-font-smoothing: antialiased;
    }
    @media only screen and (max-width: 640px) {
      .email-container {
        width: 100% !important;
        margin: auto !important;
        border-radius: 0 !important;
      }
      .mobile-padding {
        padding-left: 18px !important;
        padding-right: 18px !important;
      }
    }
  </style>
</head>
<body style="margin: 0; padding: 24px 0; background-color: #f1f5f9; -webkit-font-smoothing: antialiased;">

  @php
    $msgObj = isset($message) ? $message : null;
    $embedImage = function($path, $fallbackUrl) use ($msgObj) {
      if (isset($msgObj) && is_object($msgObj) && method_exists($msgObj, 'embed') && file_exists($path)) {
        return $msgObj->embed($path);
      }
      return $fallbackUrl;
    };

    $baseUrl = rtrim(config('app.url', 'https://amis.edu.ph'), '/');
    if (!str_starts_with($baseUrl, 'http')) {
      $baseUrl = 'https://amis.edu.ph';
    }

    $logo = $embedImage(
      $logoPath ?? public_path('images/AMIS_Logo.png'),
      'https://student.amis.edu.ph/images/AMIS_Logo.png'
    );
  @endphp

  <center style="width: 100%; background-color: #f1f5f9;">
    <table role="presentation" class="email-container" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="max-width: 640px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">

      <!-- ── 1. AMIS EMAIL HEADER ────────────────────────────────────────── -->
      <tr>
        <td style="background: linear-gradient(135deg, #064e3b 0%, #047857 55%, #0d9488 100%); padding: 26px 20px; text-align: center;">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
              <td align="center">
                <img src="{{ $logo }}" alt="AMIS Logo" width="76" height="76"
                     style="width: 76px; height: 76px; border-radius: 50%; background: #ffffff; padding: 4px; display: inline-block; box-shadow: 0 2px 8px rgba(0,0,0,0.15);" />
              </td>
            </tr>
            <tr>
              <td align="center" style="padding-top: 12px;">
                <p style="color: #bbf7d0; font-size: 14px; letter-spacing: 2px; font-weight: 700; margin: 0; font-family: 'Amiri', Tahoma, sans-serif;" dir="rtl">
                  المدرسة الإسلامية المنورة
                </p>
                <h1 style="color: #ffffff; font-size: 19px; font-weight: 900; letter-spacing: 1px; margin: 4px 0 0 0; text-transform: uppercase;">
                  Al Munawwara Islamic School
                </h1>
                <p style="color: #86efac; font-size: 11px; letter-spacing: 1.5px; font-weight: 800; margin: 6px 0 0 0; text-transform: uppercase;">
                  Official Academic Announcement
                </p>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- ── 2. DATE & TITLE BANNER ───────────────────────────────────────── -->
      <tr>
        <td class="mobile-padding" style="padding: 24px 28px 12px 28px; background-color: #ffffff;">
          
          <div style="display: inline-block; background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 999px; padding: 4px 12px; margin-bottom: 12px;">
            <span style="font-size: 12px; font-weight: 800; color: #047857; text-transform: uppercase; letter-spacing: 0.05em;">
              📅 September 8, 2026 • School Year 2026–2027
            </span>
          </div>

          <h2 style="font-size: 20px; font-weight: 900; color: #0f172a; margin: 0 0 12px 0; line-height: 1.35;">
            📢 Term 1 Special Awards Recognition and Parent-Teacher Conference (PTC)
          </h2>

          <div style="height: 2px; background: linear-gradient(90deg, #10b981 0%, #e2e8f0 100%); margin-bottom: 20px;"></div>

          <h3 style="font-size: 16px; font-weight: 800; color: #047857; margin: 0 0 12px 0;">
            Assalaamu alaikum wa rahmatullahi wa barakatuh{{ !empty($recipientName) && $recipientName !== 'Valued Family' ? ', ' . $recipientName : '' }}.
          </h3>

          <p style="font-size: 15px; line-height: 1.7; color: #334155; margin: 0 0 14px 0;">
            Please be informed of the <strong>Schedule and Guidelines for the Term 1 Special Awards Recognition and Parent-Teacher Conference (PTC)</strong> for <strong>SY 2026–2027</strong>.
          </p>

          <p style="font-size: 15px; line-height: 1.7; color: #334155; margin: 0 0 18px 0;">
            All students, parents/guardians, teachers, and staff are encouraged to review the attached memorandum for the complete schedule, guidelines, and important details.
          </p>

          <!-- ── 3. KEY HIGHLIGHTS BOX ──────────────────────────────────────── -->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 20px 0;">
            <tr>
              <td style="background-color: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 18px 20px;">
                <p style="margin: 0 0 10px 0; font-size: 13px; font-weight: 800; color: #047857; text-transform: uppercase; letter-spacing: 0.05em;">
                  📌 Program Components & Highlights:
                </p>
                
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tr>
                    <td style="padding: 6px 0; vertical-align: top; width: 24px; font-size: 15px;">🏆</td>
                    <td style="padding: 6px 0; font-size: 14px; color: #334155; line-height: 1.5;">
                      <strong>Term 1 Special Awards Recognition:</strong> Honoring academic achievers, character excellence, and Islamic values performance for Term 1.
                    </td>
                  </tr>
                  <tr>
                    <td style="padding: 6px 0; vertical-align: top; width: 24px; font-size: 15px;">🤝</td>
                    <td style="padding: 6px 0; font-size: 14px; color: #334155; line-height: 1.5;">
                      <strong>Parent-Teacher Conference (PTC):</strong> Consultations between parents and subject teachers / class advisers to review student progress and developmental milestones.
                    </td>
                  </tr>
                  <tr>
                    <td style="padding: 6px 0; vertical-align: top; width: 24px; font-size: 15px;">📋</td>
                    <td style="padding: 6px 0; font-size: 14px; color: #334155; line-height: 1.5;">
                      <strong>Memorandum & Schedule:</strong> Please refer to the official circular for your grade level's specific conference timeslots and guidelines.
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- ── 4. STUDENT PORTAL BUTTON ──────────────────────────────────── -->
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 20px 0;">
            <tr>
              <td align="center" style="padding: 10px 0;">
                <a href="https://student.amis.edu.ph/announcements" target="_blank"
                   style="display: inline-block; background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 800; padding: 13px 28px; border-radius: 10px; box-shadow: 0 4px 12px rgba(5,150,105,0.25);">
                  View Announcements on Student Portal →
                </a>
              </td>
            </tr>
          </table>

          <!-- Anti-trimming invisible unique token to prevent Gmail conversation truncation -->
          <div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;mso-hide:all;">
            PTC UUID: {{ (string) \Illuminate\Support\Str::uuid() }} • Time: {{ microtime(true) }}
          </div>

          <!-- ── 5. CLOSING ───────────────────────────────────────────────── -->
          <p style="margin: 0 0 8px 0; font-size: 15px; color: #334155; line-height: 1.6;">
            Thank you for your cooperation and support.
          </p>

          <p style="margin: 0 0 16px 0; font-size: 17px; font-weight: 800; color: #047857;">
            Jazakumullahu khayran.
          </p>

          <p style="margin: 0; font-size: 13px; color: #64748b; font-weight: 600; line-height: 1.5;">
            Warm regards,<br>
            <strong style="color: #0f172a;">AMIS Academic Affairs & Administration</strong><br>
            Al Munawwara Islamic School
          </p>

        </td>
      </tr>

      <!-- ── 6. FOOTER ────────────────────────────────────────────────────── -->
      <tr>
        <td class="mobile-padding" style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 22px 28px; text-align: center;">
          <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 700; color: #475569;">
            Al Munawwara Islamic School • Academic Year 2026–2027
          </p>
          <p style="margin: 0; font-size: 11px; font-weight: 500; color: #94a3b8; line-height: 1.6;">
            This is an official announcement sent to students, parents, and guardians.<br>
            Portal: <a href="https://student.amis.edu.ph" style="color: #047857; text-decoration: underline;">student.amis.edu.ph</a> • Support: <a href="mailto:amisonlinesupport@gmail.com" style="color: #047857; text-decoration: underline;">amisonlinesupport@gmail.com</a>
          </p>
        </td>
      </tr>

    </table>
  </center>

</body>
</html>
