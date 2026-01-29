<?php
/**
 * Ticket fiscal - formato térmico 80mm compatible con Dompdf
 * Variables: $issuer, $receiver, $comprobante, $items, $subtotal, $iva_total, $total,
 *            $cae, $cae_vencimiento, $qr_src, $tipo_letra, $tipo_codigo, $condicion_venta
 *            $footer_text, $footer_logo_src (opcionales)
 */
$issuer = $issuer ?? [];
$receiver = $receiver ?? [];
$comprobante = $comprobante ?? [];
$items = $items ?? [];
$subtotal = $subtotal ?? 0;
$iva_total = $iva_total ?? 0;
$total = $total ?? 0;
$cae = $cae ?? '';
$cae_vencimiento = $cae_vencimiento ?? '';
$qr_src = $qr_src ?? '';
$tipo_letra = $tipo_letra ?? 'B';
$tipo_codigo = $tipo_codigo ?? 6;
$condicion_venta = $condicion_venta ?? 'Efectivo';
$footer_text = $footer_text ?? 'Generado con Afip SDK';
$footer_logo_src = $footer_logo_src ?? 'https://afipsdk.com/faviconx32.png';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Ticket</title>
	<style type="text/css">
		@page {
			size: 80mm auto;
			margin: 0;
		}
		* {
			box-sizing: border-box;
		}
		body {
			font-family: 'DejaVu Sans', monospace, sans-serif;
			font-size: 12px;
			margin: 0;
			padding: 0;
			color: #000;
			width: 80mm;
		}
		.ticket-wrapper {
			width: 70mm;
			max-width: 70mm;
			margin: 0 auto;
			padding: 5mm 0;
		}
		.text-lg {
			font-size: 18px;
			font-weight: bold;
		}
		.text-center {
			text-align: center;
		}
		.text-right {
			text-align: right;
		}
		p {
			margin: 2px 0;
		}
		table {
			width: 100%;
			border-collapse: collapse;
		}
		table td {
			padding: 2px 0;
			vertical-align: top;
		}
		.items-table td:last-child {
			text-align: right;
		}
		.border-top {
			border-top: 1px dashed #000;
			padding-top: 5px;
			margin-top: 5px;
		}
		.border-bottom {
			border-bottom: 1px dashed #000;
			padding-bottom: 5px;
			margin-bottom: 5px;
		}
		.total-row {
			font-size: 14px;
			font-weight: bold;
		}
		#qrcode {
			width: 50mm;
			max-width: 50mm;
			display: block;
			margin: 5px auto;
		}
		.footer {
			text-align: center;
			margin-top: 10px;
			font-size: 10px;
		}
		.footer img {
			height: 20px;
			vertical-align: middle;
		}
	</style>
</head>
<body>
	<div class="ticket-wrapper">
		<!-- Emisor -->
		<div class="border-bottom">
			<p><strong><?= htmlspecialchars($issuer['razon_social'] ?? '') ?></strong></p>
			<p><?= htmlspecialchars($issuer['domicilio'] ?? '') ?></p>
			<p>C.U.I.T.: <?= htmlspecialchars($issuer['cuit'] ?? '') ?></p>
			<p><?= htmlspecialchars(strtoupper($issuer['condicion_iva'] ?? 'RESPONSABLE INSCRIPTO')) ?></p>
			<?php if (!empty($issuer['iibb'])): ?>
			<p>IIBB: <?= htmlspecialchars($issuer['iibb']) ?></p>
			<?php endif; ?>
			<?php if (!empty($issuer['inicio_actividad'])): ?>
			<p>Inicio Act.: <?= htmlspecialchars($issuer['inicio_actividad']) ?></p>
			<?php endif; ?>
		</div>

		<!-- Tipo comprobante -->
		<div class="text-center border-bottom">
			<p class="text-lg">FACTURA <?= htmlspecialchars($tipo_letra) ?></p>
			<p>Código <?= (int) $tipo_codigo ?></p>
			<p>P.V: <?= htmlspecialchars($comprobante['pto_vta'] ?? '') ?> | Nro: <?= htmlspecialchars($comprobante['nro'] ?? '') ?></p>
			<p>Fecha: <?= htmlspecialchars($comprobante['fecha'] ?? '') ?></p>
			<p>Concepto: <?= htmlspecialchars($comprobante['concepto_texto'] ?? 'Productos') ?></p>
		</div>

		<!-- Receptor -->
		<div class="border-bottom">
			<p>A CONSUMIDOR FINAL</p>
		</div>

		<!-- Items -->
		<div class="border-bottom">
			<table class="items-table">
				<?php foreach ($items as $item): ?>
				<tr>
					<td><?= (int) ($item['quantity'] ?? $item['cantidad'] ?? 1) ?></td>
					<td><?= htmlspecialchars($item['description'] ?? $item['descripcion'] ?? '') ?></td>
					<td><?= htmlspecialchars($item['taxRate'] ?? $item['iva_pct'] ?? '21') ?>%</td>
					<td><?= number_format((float) ($item['unitPrice'] ?? $item['subtotal'] ?? 0), 2, ',', '.') ?></td>
				</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<!-- Total -->
		<div class="border-bottom">
			<table>
				<tr class="total-row">
					<td>TOTAL</td>
					<td class="text-right">$ <?= number_format((float) $total, 2, ',', '.') ?></td>
				</tr>
			</table>
		</div>

		<!-- CAE -->
		<div class="text-center">
			<p>CAE: <?= htmlspecialchars($cae) ?></p>
			<p>Vto: <?= htmlspecialchars($cae_vencimiento) ?></p>
		</div>

		<!-- QR -->
		<?php if ($qr_src !== ''): ?>
		<div class="text-center">
			<img id="qrcode" src="<?= htmlspecialchars($qr_src) ?>" alt="QR AFIP" />
		</div>
		<?php endif; ?>

		<!-- Footer -->
		<div class="footer">
			<?php if ($footer_text !== ''): ?>
			<span><?= htmlspecialchars($footer_text) ?></span>
			<?php endif; ?>
			<?php if ($footer_logo_src !== ''): ?>
			<img src="<?= htmlspecialchars($footer_logo_src) ?>" alt="" />
			<?php endif; ?>
		</div>
	</div>
</body>
</html>
