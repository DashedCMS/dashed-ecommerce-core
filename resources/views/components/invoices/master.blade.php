<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge"
    >

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        {{ $title ?: 'Factuur' }}
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Helvetica, sans-serif;
            color: #44403c;
        }

        h1 {
            color: {{Translation::get('primary-color-code', 'emails', '#A0131C')}};
            font-size: 1.5rem;
            margin: 0;
        }

        s {
            color: #78716c;
            font-size: 0.875rem;
            vertical-align: bottom;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
        }

        .logo {
            width: 100%;
            float: right;
            max-width: 8rem;
        }

        .sender {
            padding: 1rem;
            background-color: #f5f5f4;
        }

        .receiver {
            padding-top: 1rem;
        }

        .table-dates {
            table-layout: fixed;
            margin-top: 2rem;
        }

        .divider {
            border: 0.25rem solid {{Translation::get('primary-color-code', 'emails', '#A0131C')}};
        }

        .table-note {
            width: 75%;
            margin-bottom: 2rem;
            font-size: 0.875rem;
        }

        .table-note th {
            color: {{Translation::get('primary-color-code', 'emails', '#A0131C')}};
        }

        .table-details {
            margin-top: 2rem;
            margin-bottom: 2rem;
            table-layout: fixed;
        }

        .table-details td {
            vertical-align: top;
        }

        h2 {
            font-size: 1rem;
            margin: 2rem 0 0 0;
        }

        .table-details h2 {
            color: {{Translation::get('primary-color-code', 'emails', '#A0131C')}};
            font-size: 1rem;
            margin: 0;
        }

        .table-details p {
            margin: 0.5rem 0 0 0;
        }

        .order {
            border-top: 1px solid {{Translation::get('primary-color-code', 'emails', '#A0131C')}};
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .order h2,
        .order h3 {
            @apply font-bold;
            color: {{Translation::get('primary-color-code', 'emails', '#A0131C')}};
            font-size: 1rem;
            margin: 0;
        }

        .order table {
            margin-top: 1rem;
            table-layout: fixed;
        }

        .order table th {
            padding: 0 0 0.5rem 0;
            font-size: 0.875rem;
        }

        .order table td {
            border-top: 1px solid #d6d3d1;
            padding: 0.5rem 0;
        }

        .numeric {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .total {
            text-align: right;
            font-weight: bold;
            font-variant-numeric: tabular-nums;
        }

        .tree {
            display: inline-block;
            width: 8px;
            height: 8px;
            margin-bottom: 4px;
            border-left: 1px solid black;
            border-bottom: 1px solid black;
        }

        .min-margin{
            margin-top: -0.5rem;
        }
    </style>
</head>

<body>
{{ $slot }}
</body>
</html>
