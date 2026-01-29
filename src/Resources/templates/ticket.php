<?php
/**
 * Ticket fiscal - igual a referencia (8cm, monospace 12px, líneas dashed)
 * Variables: $issuer, $receiver, $comprobante, $items, $subtotal, $iva_total, $total,
 *            $cae, $cae_vencimiento, $qr_src, $tipo_letra, $tipo_codigo, $condicion_venta
 *            $footer_text, $footer_logo_src (opcionales; si no se pasan, se muestra "Generado con Afip SDK" + logo)
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
		* {
			box-sizing: border-box;
			-webkit-user-select: none;
			-moz-user-select: none;
			-ms-user-select: none;
			user-select: none;
		}
		.bill-container {
			border-collapse: collapse;
			max-width: 8cm;
			position: absolute;
			left: 0;
			right: 0;
			margin: auto;
			font-family: monospace;
			font-size: 12px;
		}
		.text-lg {
			font-size: 20px;
		}
		.text-center {
			text-align: center;
		}
		#qrcode {
			width: 75%;
		}
		p {
			margin: 2px 0;
		}
		table table {
			width: 100%;
		}
		table table tr td:last-child {
			text-align: right;
		}
		.border-top {
			border-top: 1px dashed;
		}
		.padding-b-3 {
			padding-bottom: 3px;
		}
		.padding-t-3 {
			padding-top: 3px;
		}
	</style>
</head>
<body>
	<table class="bill-container">
		<tr>
			<td class="padding-b-3">
				<p>Razón social: <?= htmlspecialchars($issuer['razon_social'] ?? '') ?></p>
				<p>Direccion: <?= htmlspecialchars($issuer['domicilio'] ?? '') ?></p>
				<p>C.U.I.T.: <?= htmlspecialchars($issuer['cuit'] ?? '') ?></p>
				<p><?= htmlspecialchars(strtoupper($issuer['condicion_iva'] ?? 'RESPONSABLE INSCRIPTO')) ?></p>
				<?php if (!empty($issuer['iibb'])): ?>
				<p>IIBB: <?= htmlspecialchars($issuer['iibb']) ?></p>
				<?php endif; ?>
				<?php if (!empty($issuer['inicio_actividad'])): ?>
				<p>Inicio de actividad: <?= htmlspecialchars($issuer['inicio_actividad']) ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<td class="border-top padding-t-3 padding-b-3">
				<p class="text-center text-lg">FACTURA <?= htmlspecialchars($tipo_letra) ?></p>
				<p class="text-center">Codigo <?= (int) $tipo_codigo ?></p>
				<p>P.V: <?= htmlspecialchars($comprobante['pto_vta'] ?? '') ?></p>
				<p>Nro: <?= htmlspecialchars($comprobante['nro'] ?? '') ?></p>
				<p>Fecha: <?= htmlspecialchars($comprobante['fecha'] ?? '') ?></p>
				<p>Concepto: <?= htmlspecialchars($comprobante['concepto_texto'] ?? 'Productos') ?></p>
			</td>
		</tr>
		<tr>
			<td class="border-top padding-t-3 padding-b-3">
				<p>A CONSUMIDOR FINAL</p>
			</td>
		</tr>
		<tr>
			<td class="border-top padding-t-3 padding-b-3">
				<div>
					<table>
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
			</td>
		</tr>
		<tr>
			<td class="border-top padding-t-3 padding-b-3">
				<div>
					<table>
						<tr>
							<td>TOTAL</td>
							<td><?= number_format((float) $total, 2, ',', '.') ?></td>
						</tr>
					</table>
				</div>
			</td>
		</tr>
		<tr>
			<td class="border-top padding-t-3">
				<p>CAE: <?= htmlspecialchars($cae) ?></p>
				<p>Vto: <?= htmlspecialchars($cae_vencimiento) ?></p>
			</td>
		</tr>
		<?php if ($qr_src !== ''): ?>
		<tr class="text-center">
			<td>
				<img id="qrcode" src="<?= htmlspecialchars($qr_src) ?>" alt="QR AFIP" />
			</td>
		</tr>
		<?php endif; ?>
		<tr class="bill-row row-details">
			<td style="margin-bottom: 10px; text-align: center;">
				<?php if ($footer_text !== ''): ?>
				<span style="vertical-align: bottom;"><?= htmlspecialchars($footer_text) ?></span>
				<?php endif; ?>
				<?php if ($footer_logo_src !== ''): ?>
				<img style="height: 20px; vertical-align: middle;" src="<?= htmlspecialchars($footer_logo_src) ?>" alt="" />
				<?php endif; ?>
			</td>
		</tr>
	</table>
</body>
</html>
