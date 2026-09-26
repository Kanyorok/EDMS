<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>{{ $title }}</title>
    <style>
        @page {
            size: auto;
            margin: 0mm;
        }

        table {
            width: 100%;
            margin-bottom: 20px;
        }

        #activities {
            font-family: Arial, Helvetica, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }

        #activities td, #customers th {
            border: 1px solid #ddd;
            padding: 8px;
        }

        #activities tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        #activities tr:hover {
            background-color: #ddd;
        }

        #activities th {
            padding-top: 12px;
            padding-bottom: 12px;
            text-align: left;
            background-color: #2d3091;
            color: white;
        }

        .form-container {
            margin: 20px;
        }

        .header-row h1, h3, p {
            text-align: center;
            padding: 0px;
            margin: 0px;
        }

        .headoffice-row p, h1 {
            text-align: start;
            padding: 0px;
            margin: 0px;
        }

        @media print {
            header, footer, .no-print {
                display: none;
            }

            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .content {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .page-break {
                page-break-before: always;
            }

        }
    </style>
</head>
<body>
<div>
    <div id="MainContainer" class="form-container">
        @include('documents.partials.letterhead')
        <hr>
        <h2 style="text-align: center; margin:5px 0;">{{  $title  }}</h2>

        <table style="border: 1px" id="activities">
            <thead>
            <tr>
                <th>Message ID</th>
                <th>User ID</th>
                <th>Staff Name</th>
                <th>Phone No</th>
                <th>{{ __('common.status') }}</th>
                <th>{{ __('common.date') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($notifications->sms as $notification)
                <tr>
                    <td>{{ \Illuminate\Support\Str::upper($notification->SMSId) }}.</td>
                    <td>{{ ($notification->party instanceof \App\Models\Auth\User)?$notification->party->UserID:'?'  }}</td>
                    <td>{{ ($notification->party instanceof \App\Models\Auth\User)?$notification->party->Name:'?' }}</td>
                    <td>{{ ($notification->Phone)??'?' }}</td>
                    <td>{{ $notification->Status->name }}</td>
                    <td>{{ ($notification->Dated)?$notification->Dated->format('M d, Y h:i a'): $notification->CreatedOn->format('M d, Y h:i a') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

    </div>
</div>
</body>
</html>
