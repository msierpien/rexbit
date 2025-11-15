// OrderTimeline.jsx - Timeline i historia zamówienia (jak sekcja "Wymiana wiadomości" w BaseLinker)

import React, { useState } from 'react';
import { 
    ChevronDown, 
    ChevronRight, 
    MessageSquare, 
    Clock, 
    User, 
    Package, 
    Truck, 
    CreditCard,
    Edit,
    Send,
    Plus
} from 'lucide-react';

const EVENT_ICONS = {
    status_change: <Package className="w-4 h-4" />,
    payment: <CreditCard className="w-4 h-4" />,
    shipping: <Truck className="w-4 h-4" />,
    message: <MessageSquare className="w-4 h-4" />,
    note: <Edit className="w-4 h-4" />,
    system: <Clock className="w-4 h-4" />
};

const EVENT_COLORS = {
    status_change: 'text-blue-600 bg-blue-100',
    payment: 'text-green-600 bg-green-100',
    shipping: 'text-purple-600 bg-purple-100',
    message: 'text-orange-600 bg-orange-100',
    note: 'text-gray-600 bg-gray-100',
    system: 'text-indigo-600 bg-indigo-100'
};

export default function OrderTimeline({ history = [], messages = [], expanded, onToggle }) {
    const [newMessage, setNewMessage] = useState('');
    const [newNote, setNewNote] = useState('');
    const [showMessageForm, setShowMessageForm] = useState(false);
    const [showNoteForm, setShowNoteForm] = useState(false);

    // Połączenie i sortowanie historii i wiadomości
    const allEvents = [
        ...history.map(item => ({
            ...item,
            type: 'status_change',
            timestamp: item.created_at,
            icon: EVENT_ICONS.status_change,
            title: `Status zmieniony na: ${item.to_status}`,
            description: item.comment,
            user: item.changed_by || 'System'
        })),
        ...messages.map(item => ({
            ...item,
            type: 'message',
            timestamp: item.created_at,
            icon: EVENT_ICONS.message,
            title: item.subject || 'Wiadomość',
            description: item.content,
            user: item.from_user || item.from_email
        }))
    ].sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

    const handleSendMessage = () => {
        if (newMessage.trim()) {
            // Tu będzie logika wysyłania wiadomości
            console.log('Send message:', newMessage);
            setNewMessage('');
            setShowMessageForm(false);
        }
    };

    const handleAddNote = () => {
        if (newNote.trim()) {
            // Tu będzie logika dodawania notatki
            console.log('Add note:', newNote);
            setNewNote('');
            setShowNoteForm(false);
        }
    };

    const formatTimestamp = (timestamp) => {
        return new Date(timestamp).toLocaleString('pl-PL', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    return (
        <div className="space-y-4">
            {/* Wymiana wiadomości */}
            <div className="bg-white rounded-lg shadow">
                <div className="px-6 py-4 border-b border-gray-200">
                    <button
                        onClick={() => onToggle('messages')}
                        className="flex items-center justify-between w-full text-left"
                    >
                        <div className="flex items-center">
                            <MessageSquare className="w-5 h-5 text-gray-400 mr-2" />
                            <h3 className="text-lg font-medium text-gray-900">Wymiana wiadomości</h3>
                            <span className="ml-2 px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">
                                {messages.length}
                            </span>
                        </div>
                        {expanded.messages ? 
                            <ChevronDown className="w-5 h-5 text-gray-400" /> : 
                            <ChevronRight className="w-5 h-5 text-gray-400" />
                        }
                    </button>
                </div>

                {expanded.messages && (
                    <div className="px-6 py-4">
                        {/* Przyciski akcji */}
                        <div className="flex space-x-2 mb-4">
                            <button
                                onClick={() => setShowMessageForm(!showMessageForm)}
                                className="inline-flex items-center px-3 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                            >
                                <MessageSquare className="w-4 h-4 mr-1" />
                                Nowa wiadomość
                            </button>
                            <button
                                onClick={() => setShowNoteForm(!showNoteForm)}
                                className="inline-flex items-center px-3 py-2 text-sm bg-gray-600 text-white rounded-md hover:bg-gray-700"
                            >
                                <Edit className="w-4 h-4 mr-1" />
                                Dodaj notatkę
                            </button>
                        </div>

                        {/* Formularz nowej wiadomości */}
                        {showMessageForm && (
                            <div className="mb-4 p-4 bg-blue-50 rounded-lg border-l-4 border-blue-200">
                                <h4 className="text-sm font-medium text-gray-900 mb-2">Nowa wiadomość</h4>
                                <textarea
                                    value={newMessage}
                                    onChange={(e) => setNewMessage(e.target.value)}
                                    placeholder="Napisz wiadomość..."
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    rows={3}
                                />
                                <div className="flex justify-end space-x-2 mt-2">
                                    <button
                                        onClick={() => setShowMessageForm(false)}
                                        className="px-3 py-1 text-sm text-gray-600 hover:text-gray-800"
                                    >
                                        Anuluj
                                    </button>
                                    <button
                                        onClick={handleSendMessage}
                                        className="inline-flex items-center px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700"
                                    >
                                        <Send className="w-3 h-3 mr-1" />
                                        Wyślij
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* Formularz nowej notatki */}
                        {showNoteForm && (
                            <div className="mb-4 p-4 bg-gray-50 rounded-lg border-l-4 border-gray-200">
                                <h4 className="text-sm font-medium text-gray-900 mb-2">Nowa notatka wewnętrzna</h4>
                                <textarea
                                    value={newNote}
                                    onChange={(e) => setNewNote(e.target.value)}
                                    placeholder="Dodaj notatkę wewnętrzną..."
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-500"
                                    rows={3}
                                />
                                <div className="flex justify-end space-x-2 mt-2">
                                    <button
                                        onClick={() => setShowNoteForm(false)}
                                        className="px-3 py-1 text-sm text-gray-600 hover:text-gray-800"
                                    >
                                        Anuluj
                                    </button>
                                    <button
                                        onClick={handleAddNote}
                                        className="inline-flex items-center px-3 py-1 text-sm bg-gray-600 text-white rounded hover:bg-gray-700"
                                    >
                                        <Plus className="w-3 h-3 mr-1" />
                                        Dodaj
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* Lista wiadomości */}
                        {messages.length === 0 ? (
                            <div className="text-center py-8 text-gray-500">
                                <MessageSquare className="w-8 h-8 mx-auto mb-2 text-gray-300" />
                                <p>Brak wiadomości</p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {messages.map((message, index) => (
                                    <div key={index} className="flex space-x-3">
                                        <div className="flex-shrink-0">
                                            <div className="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center">
                                                <MessageSquare className="w-4 h-4 text-orange-600" />
                                            </div>
                                        </div>
                                        <div className="flex-1 bg-gray-50 rounded-lg p-3">
                                            <div className="flex items-center justify-between mb-1">
                                                <div className="text-sm font-medium text-gray-900">
                                                    {message.from_user || message.from_email}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    {formatTimestamp(message.created_at)}
                                                </div>
                                            </div>
                                            {message.subject && (
                                                <div className="text-sm font-medium text-gray-800 mb-1">
                                                    {message.subject}
                                                </div>
                                            )}
                                            <div className="text-sm text-gray-700">
                                                {message.content}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Historia zmian */}
            <div className="bg-white rounded-lg shadow">
                <div className="px-6 py-4 border-b border-gray-200">
                    <button
                        onClick={() => onToggle('history')}
                        className="flex items-center justify-between w-full text-left"
                    >
                        <div className="flex items-center">
                            <Clock className="w-5 h-5 text-gray-400 mr-2" />
                            <h3 className="text-lg font-medium text-gray-900">Historia zmian</h3>
                            <span className="ml-2 px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">
                                {history.length}
                            </span>
                        </div>
                        {expanded.history ? 
                            <ChevronDown className="w-5 h-5 text-gray-400" /> : 
                            <ChevronRight className="w-5 h-5 text-gray-400" />
                        }
                    </button>
                </div>

                {expanded.history && (
                    <div className="px-6 py-4">
                        {allEvents.length === 0 ? (
                            <div className="text-center py-8 text-gray-500">
                                <Clock className="w-8 h-8 mx-auto mb-2 text-gray-300" />
                                <p>Brak historii</p>
                            </div>
                        ) : (
                            <div className="flow-root">
                                <ul className="-mb-8">
                                    {allEvents.map((event, index) => (
                                        <li key={index}>
                                            <div className="relative pb-8">
                                                {index !== allEvents.length - 1 && (
                                                    <span 
                                                        className="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" 
                                                        aria-hidden="true" 
                                                    />
                                                )}
                                                <div className="relative flex space-x-3">
                                                    <div>
                                                        <span className={`h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white ${EVENT_COLORS[event.type]}`}>
                                                            {event.icon}
                                                        </span>
                                                    </div>
                                                    <div className="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                        <div>
                                                            <p className="text-sm text-gray-900">
                                                                <span className="font-medium">{event.title}</span>
                                                                {event.description && (
                                                                    <span className="text-gray-600"> - {event.description}</span>
                                                                )}
                                                            </p>
                                                            <p className="mt-0.5 text-sm text-gray-500">
                                                                przez {event.user}
                                                            </p>
                                                        </div>
                                                        <div className="text-right text-sm whitespace-nowrap text-gray-500">
                                                            {formatTimestamp(event.timestamp)}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}