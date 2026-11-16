<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Liste des membres</title>
  <style>
    body {
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 10px;
      color: #1f2937;
    }

    .organization {
      font-size: 11px;
      color: #6b7280;
    }

    h1 {
      margin: 4px 0;
      font-size: 18px;
    }

    .scope {
      margin: 0 0 16px;
      color: #6b7280;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 5px 6px;
      border-bottom: 1px solid #d1d5db;
      text-align: left;
      word-wrap: break-word;
    }

    th {
      background-color: #f3f4f6;
      font-weight: bold;
    }

    .empty {
      padding: 16px 6px;
      text-align: center;
      color: #6b7280;
    }
  </style>
</head>
<body>
  <div class="organization">Chorale Francophone de Faravohitra</div>
  <h1>Liste des membres</h1>
  <p class="scope">{{ $scope }} · exportée le {{ $exportedAt->format('d/m/Y') }}</p>

  <table>
    <thead>
      <tr>
        @foreach ($columns as $column)
          <th>{{ $column }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $row)
        <tr>
          @foreach ($row as $cell)
            <td>{{ $cell }}</td>
          @endforeach
        </tr>
      @empty
        <tr>
          <td class="empty" colspan="{{ count($columns) }}">Aucun membre à exporter.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
