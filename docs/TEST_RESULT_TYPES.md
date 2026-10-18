# Типы результатов PHPUnit (задание «сломайте тесты»)

Запуск намеренно «сломанных» тестов:

```bash
php8.2 ./vendor/bin/phpunit --testsuite demo --display-incomplete --display-skipped
```

| Символ | Результат | Что это | Тест в `tests-demo/ResultTypesTest.php` |
|---|---|---|---|
| `.` | OK | все проверки выполнены | `testPassed` |
| `F` | Failure | проверка (`assert*`) не выполнилась — код работает «не так, как ожидали» | `testFailure` |
| `E` | Error | неожиданное исключение или фатальная ошибка | `testError` |
| `R` | Risky | тест не проверяет ничего (нет assert) или делает что-то подозрительное | `testRisky` |
| `S` | Skipped | тест пропущен (`markTestSkipped`) — например, нет нужного окружения | `testSkipped` |
| `I` | Incomplete | тест ещё не дописан (`markTestIncomplete`) | `testIncomplete` |

Ожидаемая строка прогресса для демо-набора: `.FERSI` (6 тестов: 1 успешный, 1 failure, 1 error, 1 risky, 1 skipped, 1 incomplete).

Также PHPUnit умеет `W` (Warning) и `D` (Deprecation) — их вызывают предупреждения/устаревшие вызовы PHP внутри теста;
их набор зависит от версии PHPUnit и настроек `phpunit.xml`, поэтому в демо они не включены.

> ✍️ Запустите демо-набор у себя и приложите свой вывод: я не мог выполнить PHPUnit в своей среде.
