<?php
/**
 * Ticket fiscal - igual a referencia (8cm, monospace 12px, líneas dashed)
 * Soporta Factura A (desglose IVA) y Factura B/C (IVA contenido - Ley 27.743)
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
// Datos específicos por tipo de factura
$es_factura_a = $es_factura_a ?? false;
$es_factura_b = $es_factura_b ?? true;
$es_factura_c = $es_factura_c ?? false;
$iva_contenido = $iva_contenido ?? 0;
$importe_neto_gravado = $importe_neto_gravado ?? $subtotal;
$iva_desglose = $iva_desglose ?? [];
?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<title>Ticket</title>
	<style type="text/css">
		/* IMPORTANTE: @page para Dompdf */
		@page {
			size: 80mm auto;
			margin: 0;
		}

		html,
		body {
			margin: 0;
			padding: 0;
			width: 80mm;
		}

		* {
			box-sizing: border-box;
			-webkit-user-select: none;
			-moz-user-select: none;
			-ms-user-select: none;
			user-select: none;
		}

		.bill-container {
			border-collapse: collapse;
			width: 70mm;
			max-width: 70mm;
			margin: 0 auto;
			font-family: 'DejaVu Sans', monospace, sans-serif;
			font-size: 12px;
		}

		.text-lg {
			font-size: 20px;
		}

		.text-center {
			text-align: center;
		}

		#qrcode {
			width: 75%
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
				<p><?= htmlspecialchars(strtoupper($receiver['condicion_iva'] ?? 'Consumidor final')) ?></p>
			</td>
		</tr>
		<tr>
			<td class="border-top padding-t-3 padding-b-3">
				<div>
					<table>
						<?php foreach ($items as $item):
							$cant = (int) ($item['cantidad_calc'] ?? $item['quantity'] ?? $item['cantidad'] ?? 1);
							$desc = htmlspecialchars($item['description'] ?? $item['descripcion'] ?? '');
							$alicuota = (float) ($item['alicuota_iva'] ?? $item['taxRate'] ?? $item['iva_pct'] ?? 21);
							if ($es_factura_a) {
								// Factura A: mostrar precio sin IVA
								$precio = (float) ($item['precio_unitario_calc'] ?? $item['unitPrice'] ?? 0);
								$subtotalItem = (float) ($item['subtotal_calc'] ?? ($precio * $cant));
							} else {
								// Factura B/C: mostrar precio con IVA
								$precio = (float) ($item['precio_unitario_calc'] ?? $item['unitPrice'] ?? $item['subtotal'] ?? 0);
								$subtotalItem = (float) ($item['subtotal_calc'] ?? ($precio * $cant));
							}
						?>
							<!-- Descripción en una línea -->
							<tr>
								<td colspan="4" style="padding-top: 4px;"><strong><?= $desc ?></strong></td>
							</tr>
							<?php if ($es_factura_a): ?>
							<!-- Factura A: Cantidad | Unitario | IVA | Importe -->
							<tr>
								<td><?= $cant ?></td>
								<td><?= number_format($precio, 2, ',', '.') ?></td>
								<td><?= number_format($alicuota, 0) ?>%</td>
								<td><?= number_format($subtotalItem, 2, ',', '.') ?></td>
							</tr>
							<?php else: ?>
							<!-- Factura B/C: Cantidad | Unitario | Importe (sin IVA) -->
							<tr>
								<td><?= $cant ?></td>
								<td><?= number_format($precio, 2, ',', '.') ?></td>
								<td></td>
								<td><?= number_format($subtotalItem, 2, ',', '.') ?></td>
							</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</table>
				</div>
			</td>
		</tr>
		<tr>
			<td class="border-top padding-t-3 padding-b-3">
				<div>
					<?php if ($es_factura_a): ?>
					<!-- FACTURA A: Desglose de IVA -->
					<table>
						<tr>
							<td>Neto Gravado</td>
							<td><?= number_format((float) $importe_neto_gravado, 2, ',', '.') ?></td>
						</tr>
						<?php
						$alicuotasAfip = ['27.0' => '27%', '21.0' => '21%', '10.5' => '10,5%', '5.0' => '5%', '2.5' => '2,5%', '0.0' => '0%'];
						foreach ($alicuotasAfip as $key => $label):
							$valor = $iva_desglose[$key] ?? 0;
							if ($valor > 0):
						?>
						<tr>
							<td>IVA <?= $label ?></td>
							<td><?= number_format((float) $valor, 2, ',', '.') ?></td>
						</tr>
						<?php endif; endforeach; ?>
						<tr>
							<td><strong>TOTAL</strong></td>
							<td><strong><?= number_format((float) $total, 2, ',', '.') ?></strong></td>
						</tr>
					</table>
					<?php else: ?>
					<!-- FACTURA B/C: IVA Contenido -->
					<table>
						<tr>
							<td><strong>TOTAL</strong></td>
							<td><strong><?= number_format((float) $total, 2, ',', '.') ?></strong></td>
						</tr>
					</table>
					<p style="font-size: 10px; margin-top: 4px;">
						Ley 27.743 - IVA Contenido: $<?= number_format((float) $iva_contenido, 2, ',', '.') ?>
					</p>
					<?php endif; ?>
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
	</table>
</body>

</html>