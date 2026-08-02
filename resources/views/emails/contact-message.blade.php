{{ $message->name }} <{{ $message->email }}> wrote:

{{ $message->body }}

--
Received {{ $message->created_at->toDayDateTimeString() }} from {{ $message->ip_address }}
