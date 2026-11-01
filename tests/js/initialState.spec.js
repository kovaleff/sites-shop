import { consumeInitialState, readInitialState } from '@/lib/initialState';

describe('initialState', () => {
    afterEach(() => {
        delete window.__INITIAL_STATE__;
    });

    it('возвращает пустой объект, если состояние не передано', () => {
        expect(readInitialState()).toEqual({});
        expect(consumeInitialState('listing')).toBeNull();
    });

    it('читает состояние, переданное сервером', () => {
        window.__INITIAL_STATE__ = { listing: { id: 7, slug: '7-lot' } };

        expect(readInitialState()).toEqual({ listing: { id: 7, slug: '7-lot' } });
    });

    it('удаляет значение после использования', () => {
        window.__INITIAL_STATE__ = { listing: { id: 7 }, other: { id: 1 } };

        expect(consumeInitialState('listing')).toEqual({ id: 7 });
        expect(consumeInitialState('listing')).toBeNull();
        expect(window.__INITIAL_STATE__).toEqual({ other: { id: 1 } });
    });

    it('не отдаёт не-объекты', () => {
        window.__INITIAL_STATE__ = { listing: 'строка' };

        expect(consumeInitialState('listing')).toBeNull();
    });
});
