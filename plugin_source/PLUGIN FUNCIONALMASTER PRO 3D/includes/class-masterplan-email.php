<?php
/**
 * Handler de Email con Templates HTML de Lujo
 * Incluye email al admin y confirmación al cliente
 *
 * @package MasterPlan_3D_Pro
 */

class Masterplan_Email
{

    /**
     * Enviar email de consulta sobre un lote
     * Envía tanto al admin como confirmación al cliente
     */
    public static function send_lot_inquiry($lot_id, $customer_data)
    {
        // Obtener datos del lote
        $lot = get_post($lot_id);
        if (!$lot) {
            return false;
        }

        $lot_number = get_post_meta($lot_id, '_lot_number', true);
        $price = get_post_meta($lot_id, '_lot_price', true);
        $area = get_post_meta($lot_id, '_lot_area', true);
        $status = get_post_meta($lot_id, '_lot_status', true);
        $thumbnail_url = get_the_post_thumbnail_url($lot_id, 'large');
        $project_id = get_post_meta($lot_id, '_project_id', true);
        $project_name = $project_id ? get_the_title($project_id) : '';

        // Formatear precio colombiano
        $formatted_price = number_format($price, 0, ',', '.');

        // Datos del cliente
        $customer_name = sanitize_text_field($customer_data['name']);
        $customer_email = sanitize_email($customer_data['email']);
        $customer_phone = sanitize_text_field($customer_data['phone']);
        $customer_message = sanitize_textarea_field($customer_data['message'] ?? '');

        // Datos comunes
        $email_data = array(
            'lot_number' => $lot_number,
            'lot_title' => $lot->post_title,
            'project_name' => $project_name,
            'price' => $formatted_price,
            'area' => $area,
            'status' => self::get_status_label($status),
            'thumbnail_url' => $thumbnail_url,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'customer_message' => $customer_message,
            'company_name' => get_option('masterplan_email_from_name', get_bloginfo('name')),
        );

        // 1. Enviar email al administrador
        $admin_sent = self::send_admin_notification($email_data);

        // 2. Enviar email de confirmación al cliente
        $customer_sent = self::send_customer_confirmation($email_data);

        return $admin_sent;
    }

    /**
     * Enviar notificación al administrador
     */
    private static function send_admin_notification($data)
    {
        $to = get_option('masterplan_email_from', get_option('admin_email'));
        $subject = '🏡 Nueva Consulta - Lote ' . $data['lot_number'] . ' | ' . $data['project_name'];

        $html_content = self::get_admin_template($data);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $data['company_name'] . ' <' . $to . '>',
            'Reply-To: ' . $data['customer_name'] . ' <' . $data['customer_email'] . '>',
        );

        return wp_mail($to, $subject, $html_content, $headers);
    }

    /**
     * Enviar confirmación al cliente
     */
    private static function send_customer_confirmation($data)
    {
        $to = $data['customer_email'];
        $subject = '🎉 ¡Excelente elección, ' . $data['customer_name'] . '! - ' . $data['company_name'];

        $from_email = get_option('masterplan_email_from', get_option('admin_email'));

        $html_content = self::get_customer_template($data);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $data['company_name'] . ' <' . $from_email . '>',
        );

        return wp_mail($to, $subject, $html_content, $headers);
    }

    /**
     * Template para el administrador
     */
    private static function get_admin_template($data)
    {
        ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 300; letter-spacing: 2px;">
                                🏡 NUEVA CONSULTA
                            </h1>
                            <p style="margin: 10px 0 0; color: #e0e7ff; font-size: 16px;">
                                <?php echo esc_html($data['project_name']); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Image -->
                    <?php if ($data['thumbnail_url']): ?>
                    <tr>
                        <td style="padding: 0;">
                            <img src="<?php echo esc_url($data['thumbnail_url']); ?>" alt="<?php echo esc_attr($data['lot_title']); ?>" style="width: 100%; height: 250px; object-fit: cover; display: block;">
                        </td>
                    </tr>
                    <?php
        endif; ?>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="margin: 0 0 25px; color: #1a202c; font-size: 24px; font-weight: 600;">
                                Lote <?php echo esc_html($data['lot_number']); ?>
                            </h2>

                            <!-- Lot Details -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 30px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                                <tr style="background-color: #f7fafc;">
                                    <td style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #4a5568;">Precio</td>
                                    <td style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; color: #10b981; font-weight: 700; font-size: 22px;">$ <?php echo esc_html($data['price']); ?> COP</td>
                                </tr>
                                <tr>
                                    <td style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #4a5568;">Área</td>
                                    <td style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; color: #1a202c;"><?php echo esc_html($data['area']); ?> m²</td>
                                </tr>
                                <tr style="background-color: #f7fafc;">
                                    <td style="padding: 15px 20px; font-weight: 600; color: #4a5568;">Estado</td>
                                    <td style="padding: 15px 20px; color: #1a202c;"><?php echo esc_html($data['status']); ?></td>
                                </tr>
                            </table>

                            <!-- Customer Info -->
                            <div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 25px; border-radius: 8px; border-left: 4px solid #3b82f6; margin-bottom: 20px;">
                                <h3 style="margin: 0 0 15px; color: #1e40af; font-size: 18px;">👤 Datos del Cliente</h3>
                                <p style="margin: 0 0 8px; color: #334155;"><strong>Nombre:</strong> <?php echo esc_html($data['customer_name']); ?></p>
                                <p style="margin: 0 0 8px; color: #334155;"><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($data['customer_email']); ?>" style="color: #3b82f6;"><?php echo esc_html($data['customer_email']); ?></a></p>
                                <p style="margin: 0; color: #334155;"><strong>Teléfono:</strong> <a href="tel:<?php echo esc_attr($data['customer_phone']); ?>" style="color: #3b82f6;"><?php echo esc_html($data['customer_phone']); ?></a></p>
                            </div>

                            <?php if ($data['customer_message']): ?>
                            <div style="background-color: #fefce8; padding: 20px; border-radius: 8px; border-left: 4px solid #eab308;">
                                <h3 style="margin: 0 0 10px; color: #854d0e; font-size: 16px;">💬 Mensaje</h3>
                                <p style="margin: 0; color: #713f12; line-height: 1.6;"><?php echo nl2br(esc_html($data['customer_message'])); ?></p>
                            </div>
                            <?php
        endif; ?>

                            <div style="text-align: center; margin-top: 30px;">
                                <a href="tel:<?php echo esc_attr($data['customer_phone']); ?>" style="display: inline-block; background: #10b981; color: white; padding: 14px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-right: 10px;">📞 Llamar</a>
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $data['customer_phone']); ?>" style="display: inline-block; background: #25d366; color: white; padding: 14px 30px; border-radius: 8px; text-decoration: none; font-weight: 600;">💬 WhatsApp</a>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #1e293b; padding: 25px; text-align: center;">
                            <p style="margin: 0; color: #94a3b8; font-size: 13px;">
                                © <?php echo date('Y'); ?> <?php echo esc_html($data['company_name']); ?> | Powered by MasterPlan 3D Pro
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
<?php
        return ob_get_clean();
    }

    /**
     * Template de confirmación para el cliente (copy persuasivo)
     */
    private static function get_customer_template($data)
    {
        ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">

                    <!-- Header Celebratorio -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 50px 30px; text-align: center;">
                            <div style="font-size: 60px; margin-bottom: 15px;">🎉</div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 600;">
                                ¡Excelente Elección!
                            </h1>
                            <p style="margin: 15px 0 0; color: #d1fae5; font-size: 18px;">
                                Has dado el primer paso hacia tu nuevo hogar
                            </p>
                        </td>
                    </tr>

                    <!-- Saludo Personalizado -->
                    <tr>
                        <td style="padding: 40px 30px 20px;">
                            <p style="margin: 0; color: #374151; font-size: 18px; line-height: 1.6;">
                                Hola <strong><?php echo esc_html($data['customer_name']); ?></strong>,
                            </p>
                            <p style="margin: 20px 0 0; color: #4b5563; font-size: 16px; line-height: 1.8;">
                                ¡Nos emociona saber que estás interesado en el <strong>Lote <?php echo esc_html($data['lot_number']); ?></strong><?php echo $data['project_name'] ? ' del proyecto <strong>' . esc_html($data['project_name']) . '</strong>' : ''; ?>!
                                Has tomado una excelente decisión al elegir esta ubicación privilegiada.
                            </p>
                        </td>
                    </tr>

                    <!-- Imagen del Lote -->
                    <?php if ($data['thumbnail_url']): ?>
                    <tr>
                        <td style="padding: 0 30px;">
                            <img src="<?php echo esc_url($data['thumbnail_url']); ?>" alt="Tu futuro lote" style="width: 100%; height: 250px; object-fit: cover; border-radius: 12px; display: block;">
                        </td>
                    </tr>
                    <?php
        endif; ?>

                    <!-- Detalles del Lote -->
                    <tr>
                        <td style="padding: 30px;">
                            <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); padding: 30px; border-radius: 12px; border: 2px solid #86efac;">
                                <h2 style="margin: 0 0 20px; color: #166534; font-size: 22px; text-align: center;">
                                    📋 Tu Lote Seleccionado
                                </h2>

                                <table width="100%" style="margin-bottom: 0;">
                                    <tr>
                                        <td style="padding: 12px 0; border-bottom: 1px dashed #86efac;">
                                            <span style="color: #4b5563;">Número de Lote</span>
                                        </td>
                                        <td style="padding: 12px 0; border-bottom: 1px dashed #86efac; text-align: right;">
                                            <strong style="color: #166534; font-size: 18px;"><?php echo esc_html($data['lot_number']); ?></strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px 0; border-bottom: 1px dashed #86efac;">
                                            <span style="color: #4b5563;">Área</span>
                                        </td>
                                        <td style="padding: 12px 0; border-bottom: 1px dashed #86efac; text-align: right;">
                                            <strong style="color: #1e293b;"><?php echo esc_html($data['area']); ?> m²</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 15px 0;">
                                            <span style="color: #4b5563; font-size: 18px;">💰 Precio</span>
                                        </td>
                                        <td style="padding: 15px 0; text-align: right;">
                                            <strong style="color: #166534; font-size: 26px;">$ <?php echo esc_html($data['price']); ?></strong>
                                            <span style="color: #4b5563; font-size: 14px;"> COP</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>

                    <!-- Mensaje Persuasivo -->
                    <tr>
                        <td style="padding: 0 30px 30px;">
                            <div style="background-color: #fef3c7; padding: 25px; border-radius: 12px; border-left: 4px solid #f59e0b;">
                                <h3 style="margin: 0 0 10px; color: #92400e; font-size: 18px;">⏰ ¡No dejes pasar esta oportunidad!</h3>
                                <p style="margin: 0; color: #78350f; line-height: 1.6;">
                                    Los lotes en esta zona tienen alta demanda. Nuestro equipo se pondrá en contacto contigo
                                    <strong>en las próximas 24 horas</strong> para brindarte toda la información que necesitas
                                    y resolver cualquier duda.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Próximos Pasos -->
                    <tr>
                        <td style="padding: 0 30px 30px;">
                            <h3 style="margin: 0 0 20px; color: #1e293b; font-size: 20px;">📌 Próximos Pasos</h3>

                            <table width="100%">
                                <tr>
                                    <td style="padding: 15px; background: #f8fafc; border-radius: 8px; margin-bottom: 10px;">
                                        <table>
                                            <tr>
                                                <td style="width: 50px; vertical-align: top;">
                                                    <div style="background: #667eea; color: white; width: 30px; height: 30px; border-radius: 50%; text-align: center; line-height: 30px; font-weight: bold;">1</div>
                                                </td>
                                                <td style="color: #475569;">
                                                    <strong>Recibirás una llamada</strong> de nuestro asesor especializado
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr><td style="height: 10px;"></td></tr>
                                <tr>
                                    <td style="padding: 15px; background: #f8fafc; border-radius: 8px;">
                                        <table>
                                            <tr>
                                                <td style="width: 50px; vertical-align: top;">
                                                    <div style="background: #667eea; color: white; width: 30px; height: 30px; border-radius: 50%; text-align: center; line-height: 30px; font-weight: bold;">2</div>
                                                </td>
                                                <td style="color: #475569;">
                                                    <strong>Coordinaremos una visita</strong> para que conozcas el terreno
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr><td style="height: 10px;"></td></tr>
                                <tr>
                                    <td style="padding: 15px; background: #f8fafc; border-radius: 8px;">
                                        <table>
                                            <tr>
                                                <td style="width: 50px; vertical-align: top;">
                                                    <div style="background: #667eea; color: white; width: 30px; height: 30px; border-radius: 50%; text-align: center; line-height: 30px; font-weight: bold;">3</div>
                                                </td>
                                                <td style="color: #475569;">
                                                    <strong>Te presentaremos opciones de financiamiento</strong> a tu medida
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- CTA WhatsApp -->
                    <tr>
                        <td style="padding: 0 30px 40px; text-align: center;">
                            <p style="margin: 0 0 20px; color: #64748b;">¿Tienes preguntas urgentes? Escríbenos directamente:</p>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', get_option('masterplan_whatsapp_number', '')); ?>?text=Hola!%20Estoy%20interesado%20en%20el%20Lote%20<?php echo urlencode($data['lot_number']); ?>"
                               style="display: inline-block; background: #25d366; color: white; padding: 16px 40px; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 16px; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);">
                                💬 Escribir por WhatsApp
                            </a>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 30px; text-align: center;">
                            <p style="margin: 0 0 10px; color: #e2e8f0; font-size: 16px; font-weight: 600;">
                                <?php echo esc_html($data['company_name']); ?>
                            </p>
                            <p style="margin: 0 0 15px; color: #94a3b8; font-size: 13px;">
                                Construyendo el futuro, un lote a la vez.
                            </p>
                            <p style="margin: 0; color: #64748b; font-size: 12px;">
                                © <?php echo date('Y'); ?> Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
<?php
        return ob_get_clean();
    }

    /**
     * Obtener etiqueta del estado
     */
    private static function get_status_label($status)
    {
        $labels = array(
            'disponible' => '🟢 Disponible',
            'reservado' => '🟡 Reservado',
            'vendido' => '🔴 Vendido',
        );

        return isset($labels[$status]) ? $labels[$status] : $status;
    }
}
